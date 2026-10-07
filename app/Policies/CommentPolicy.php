<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reel;
use App\Models\User;
use App\Services\PostVisibility;
use App\Services\ReelVisibility;

class CommentPolicy
{
    public function update(User $user, Comment $comment): bool
    {
        return $this->canManage($user, $comment);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->canManage($user, $comment);
    }

    public function reply(User $user, Comment $comment): bool
    {
        return $comment->parent_id === null
            && $this->targetIsPublic($comment);
    }

    private function canManage(User $user, Comment $comment): bool
    {
        if (! $this->targetIsPublic($comment)) {
            return false;
        }

        return $this->canAuthor($user, $comment->author);
    }

    private function canAuthor(User $user, object $author): bool
    {
        if ($author instanceof User) {
            return $author->is($user);
        }

        return $author instanceof Business
            && $author->status === BusinessStatus::ACTIVE
            && $author->members()->where('user_id', $user->id)->whereIn('role', [
                BusinessRole::OWNER->value,
                BusinessRole::ADMIN->value,
                BusinessRole::EDITOR->value,
            ])->exists();
    }

    private function targetIsPublic(Comment $comment): bool
    {
        $comment->loadMissing(['post', 'reel']);

        return $comment->post instanceof Post
            ? app(PostVisibility::class)->isPublic($comment->post)
            : $comment->reel instanceof Reel && app(ReelVisibility::class)->isPublic($comment->reel);
    }
}
