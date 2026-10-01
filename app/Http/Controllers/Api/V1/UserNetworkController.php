<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FollowTargetResource;
use App\Http\Resources\PublicUserResource;
use App\Models\Business;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Response;

class UserNetworkController extends Controller
{
    public function followers(User $user)
    {
        abort_if($user->account_status?->blocksAccess(), Response::HTTP_NOT_FOUND);

        $followers = User::query()
            ->where('account_status', UserStatus::ACTIVE)
            ->whereHas('follows', fn ($query) => $query->where('followable_type', $user->getMorphClass())->where('followable_id', $user->id))
            ->with('profile')
            ->latest('users.created_at')->latest('users.id')
            ->paginate(20);

        return PublicUserResource::collection($followers);
    }

    public function following(User $user)
    {
        abort_if($user->account_status?->blocksAccess(), Response::HTTP_NOT_FOUND);

        $following = Follow::query()
            ->where('follower_user_id', $user->id)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('followable_type', (new User)->getMorphClass())
                        ->whereHasMorph('followable', [User::class], fn ($targetQuery) => $targetQuery->where('account_status', UserStatus::ACTIVE));
                })->orWhere(function ($query): void {
                    $query->where('followable_type', (new Business)->getMorphClass())
                        ->whereHasMorph('followable', [Business::class], fn ($targetQuery) => $targetQuery->where('status', BusinessStatus::ACTIVE));
                });
            })
            ->with(['followable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([User::class => ['profile']]);
            }])
            ->latest('created_at')->latest('id')
            ->paginate(20);

        $following->setCollection($following->getCollection()->map(fn (Follow $follow) => $follow->followable));

        return FollowTargetResource::collection($following);
    }
}
