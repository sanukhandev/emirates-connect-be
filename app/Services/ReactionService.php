<?php

namespace App\Services;

use App\Enums\ReactionType;
use App\Events\ReactionCreated;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reel;
use App\Models\User;
use App\Support\ReactionSummary;

class ReactionService
{
    public function set(User $actor, Post|Reel|Comment $target, ReactionType $type): Post|Reel|Comment
    {
        $this->ensureInteractable($target);
        $reaction = $target->reactions()->where('user_id', $actor->id)->first();
        if ($reaction === null) {
            $reaction = $target->reactions()->create(['user_id' => $actor->id, 'type' => $type]);
            event(new ReactionCreated($reaction));
        } else {
            $reaction->update(['type' => $type]);
        }

        return ReactionSummary::load($target);
    }

    public function remove(User $actor, Post|Reel|Comment $target): void
    {
        $this->ensureInteractable($target);
        $target->reactions()->where('user_id', $actor->id)->delete();
    }

    private function ensureInteractable(Post|Reel|Comment $target): void
    {
        if ($target instanceof Post) {
            abort_unless(app(PostVisibility::class)->isPublic($target), 404);
        } elseif ($target instanceof Reel) {
            abort_unless(app(ReelVisibility::class)->isPublic($target), 404);
        } else {
            $target->loadMissing(['post', 'reel', 'parent']);
            $visible = $target->post instanceof Post
                ? app(PostVisibility::class)->isPublic($target->post)
                : $target->reel instanceof Reel && app(ReelVisibility::class)->isPublic($target->reel);
            abort_unless($visible, 404);
        }

        if ($target instanceof Comment) {
            abort_if($target->trashed() || ($target->parent && $target->parent->trashed()), 404);
        }
    }
}
