<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Comment;
use App\Models\User;
use App\Services\PostVisibility;

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
            && app(PostVisibility::class)->isPublic($comment->post);
    }

    private function canManage(User $user, Comment $comment): bool
    {
        if (! app(PostVisibility::class)->isPublic($comment->post)) {
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
}
