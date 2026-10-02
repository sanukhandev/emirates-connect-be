<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Reel;
use App\Models\User;

class ReelPolicy
{
    public function view(User $user, Reel $reel): bool
    {
        return $this->manage($user, $reel);
    }

    public function update(User $user, Reel $reel): bool
    {
        return $this->manage($user, $reel);
    }

    public function delete(User $user, Reel $reel): bool
    {
        return $this->manage($user, $reel);
    }

    private function manage(User $user, Reel $reel): bool
    {
        $author = $reel->author;
        if ($author instanceof User) {
            return $author->is($user) && $reel->created_by_user_id === $user->id;
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
