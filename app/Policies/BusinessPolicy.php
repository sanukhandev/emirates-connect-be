<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;

class BusinessPolicy
{
    public function update(User $user, Business $business): bool
    {
        return $this->activeManager($user, $business);
    }

    public function manageMembers(User $user, Business $business): bool
    {
        return $this->activeManager($user, $business);
    }

    public function addMember(User $user, Business $business, BusinessRole $role): bool
    {
        $actor = $this->role($user, $business);

        return $business->status === BusinessStatus::ACTIVE
            && (($actor === BusinessRole::OWNER && in_array($role, [BusinessRole::ADMIN, BusinessRole::EDITOR], true))
                || ($actor === BusinessRole::ADMIN && $role === BusinessRole::EDITOR));
    }

    public function updateMember(User $user, Business $business, BusinessMember $member, BusinessRole $role): bool
    {
        if ($member->business_id !== $business->id || $member->role === BusinessRole::OWNER || $business->status !== BusinessStatus::ACTIVE) {
            return false;
        }

        $actor = $this->role($user, $business);

        return $actor === BusinessRole::OWNER
            || ($actor === BusinessRole::ADMIN && $member->role === BusinessRole::EDITOR && $role === BusinessRole::EDITOR);
    }

    public function removeMember(User $user, Business $business, BusinessMember $member): bool
    {
        if ($member->business_id !== $business->id || $member->role === BusinessRole::OWNER || $business->status !== BusinessStatus::ACTIVE) {
            return false;
        }

        $actor = $this->role($user, $business);

        return $actor === BusinessRole::OWNER || ($actor === BusinessRole::ADMIN && $member->role === BusinessRole::EDITOR);
    }

    public function uploadMedia(User $user, Business $business): bool
    {
        return $this->activeManager($user, $business);
    }

    public function publish(User $user, Business $business): bool
    {
        return $business->status === BusinessStatus::ACTIVE
            && in_array($this->role($user, $business), [BusinessRole::OWNER, BusinessRole::ADMIN, BusinessRole::EDITOR], true);
    }

    public function delete(User $user, Business $business): bool
    {
        return $business->status === BusinessStatus::ACTIVE && $this->role($user, $business) === BusinessRole::OWNER;
    }

    private function activeManager(User $user, Business $business): bool
    {
        return $business->status === BusinessStatus::ACTIVE
            && in_array($this->role($user, $business), [BusinessRole::OWNER, BusinessRole::ADMIN], true);
    }

    private function role(User $user, Business $business): ?BusinessRole
    {
        $role = $business->members()->where('user_id', $user->id)->value('role');

        return $role === null ? null : ($role instanceof BusinessRole ? $role : BusinessRole::from($role));
    }
}
