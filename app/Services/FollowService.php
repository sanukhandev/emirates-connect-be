<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Events\UserFollowed;
use App\Models\Business;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FollowService
{
    public function follow(User $follower, User|Business $target): Follow
    {
        $this->ensureVisible($target);

        if ($target instanceof User && $target->is($follower)) {
            throw ValidationException::withMessages(['user' => 'You cannot follow yourself.']);
        }

        $follow = Follow::firstOrCreate([
            'follower_user_id' => $follower->id,
            'followable_type' => $target->getMorphClass(),
            'followable_id' => $target->getKey(),
        ]);
        if ($follow->wasRecentlyCreated && $target instanceof User) {
            event(new UserFollowed($follower, $target, $follow));
        }

        return $follow;
    }

    public function unfollow(User $follower, User|Business $target): void
    {
        $this->ensureVisible($target);

        Follow::query()
            ->where('follower_user_id', $follower->id)
            ->where('followable_type', $target->getMorphClass())
            ->where('followable_id', $target->getKey())
            ->delete();
    }

    public function isFollowing(User|Business $target, ?User $viewer): bool
    {
        return $viewer !== null && $this->visible($target)
            && Follow::query()
                ->where('follower_user_id', $viewer->id)
                ->where('followable_type', $target->getMorphClass())
                ->where('followable_id', $target->getKey())
                ->exists();
    }

    public function userSummary(User $target, ?User $viewer): array
    {
        return [
            'followers_count' => Follow::query()
                ->where('followable_type', $target->getMorphClass())
                ->where('followable_id', $target->id)
                ->whereHas('follower', fn ($query) => $query->where('account_status', UserStatus::ACTIVE))
                ->count(),
            'following_count' => $this->followingCount($target),
            'is_following' => $this->isFollowing($target, $viewer),
        ];
    }

    public function businessSummary(Business $target, ?User $viewer): array
    {
        return [
            'followers_count' => Follow::query()
                ->where('followable_type', $target->getMorphClass())
                ->where('followable_id', $target->id)
                ->whereHas('follower', fn ($query) => $query->where('account_status', UserStatus::ACTIVE))
                ->count(),
            'is_following' => $this->isFollowing($target, $viewer),
        ];
    }

    public function decorateUser(User $target, ?User $viewer): User
    {
        $target->setRelation('follow_summary', $this->userSummary($target, $viewer));

        return $target;
    }

    public function decorateBusiness(Business $target, ?User $viewer): Business
    {
        $target->setRelation('follow_summary', $this->businessSummary($target, $viewer));

        return $target;
    }

    public function ensureVisible(User|Business $target): void
    {
        abort_unless($this->visible($target), 404);
    }

    private function followingCount(User $target): int
    {
        return Follow::query()
            ->where('follower_user_id', $target->id)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('followable_type', (new User)->getMorphClass())
                        ->whereHasMorph('followable', [User::class], fn ($targetQuery) => $targetQuery->where('account_status', UserStatus::ACTIVE));
                })->orWhere(function ($query): void {
                    $query->where('followable_type', (new Business)->getMorphClass())
                        ->whereHasMorph('followable', [Business::class], fn ($targetQuery) => $targetQuery->where('status', BusinessStatus::ACTIVE));
                });
            })->count();
    }

    private function visible(Model $target): bool
    {
        return ($target instanceof User && $target->account_status === UserStatus::ACTIVE)
            || ($target instanceof Business && $target->status === BusinessStatus::ACTIVE);
    }
}
