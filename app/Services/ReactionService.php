<?php

namespace App\Services;

use App\Enums\ReactionType;
use App\Events\ReactionCreated;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\ReactionSummary;

class ReactionService
{
    public function set(User $actor, Post|Comment $target, ReactionType $type): Post|Comment
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

    public function remove(User $actor, Post|Comment $target): void
    {
        $this->ensureInteractable($target);
        $target->reactions()->where('user_id', $actor->id)->delete();
    }

    private function ensureInteractable(Post|Comment $target): void
    {
        $post = $target instanceof Post ? $target : $target->loadMissing(['post', 'parent'])->post;
        abort_unless($post instanceof Post && app(PostVisibility::class)->isPublic($post), 404);

        if ($target instanceof Comment) {
            abort_if($target->trashed() || ($target->parent && $target->parent->trashed()), 404);
        }
    }
}
