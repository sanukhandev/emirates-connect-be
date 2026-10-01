<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function publish(User $user, Business $business): bool
    {
        return $business->status === BusinessStatus::ACTIVE
            && $business->members()->where('user_id', $user->id)->whereIn('role', [
                BusinessRole::OWNER->value,
                BusinessRole::ADMIN->value,
                BusinessRole::EDITOR->value,
            ])->exists();
    }

    public function view(User $user, Post $post): bool
    {
        return $this->canManage($user, $post);
    }

    public function update(User $user, Post $post): bool
    {
        return $this->canManage($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->canManage($user, $post);
    }

    public function addMedia(User $user, Post $post): bool
    {
        return $this->canManage($user, $post);
    }

    public function deleteMedia(User $user, Post $post): bool
    {
        return $this->canManage($user, $post);
    }

    private function canManage(User $user, Post $post): bool
    {
        $author = $post->author;
        if ($author instanceof User) {
            return $author->is($user);
        }

        return $author instanceof Business && $this->publish($user, $author);
    }
}
