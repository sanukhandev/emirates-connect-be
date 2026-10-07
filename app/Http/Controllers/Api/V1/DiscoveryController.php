<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PublicUserResource;
use App\Models\Business;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function __invoke(Request $request, FollowService $follows): array
    {
        $user = $request->user();
        $followedUserIds = Follow::where('follower_user_id', $user->id)->where('followable_type', (new User)->getMorphClass())->pluck('followable_id');
        $users = User::query()->where('account_status', UserStatus::ACTIVE)->whereKeyNot($user->id)->whereNotIn('id', $followedUserIds)->with('profile')->latest('id')->limit(3)->get()->map(fn (User $item) => PublicUserResource::make($follows->decorateUser($item, $user))->resolve());
        $businesses = Business::query()->where('status', BusinessStatus::ACTIVE)->withCount('followerEdges')->latest('follower_edges_count')->limit(3)->get()->map(fn (Business $item) => BusinessResource::make($follows->decorateBusiness($item, $user))->resolve());
        $events = Event::query()->where('starts_at', '>=', now())->orderBy('starts_at')->withCount('attendees')->limit(3)->get();
        $trending = Post::query()->whereNotNull('body')->where('created_at', '>=', now()->subDays(30))->pluck('body')->flatMap(fn ($body) => preg_match_all('/#[[:alnum:]_]+/u', $body, $m) ? $m[0] : [])->countBy()->sortDesc()->take(5)->map(fn ($count, $tag) => ['tag' => $tag, 'posts_count' => $count])->values()->all();

        return ['users' => $users, 'businesses' => $businesses, 'trending' => $trending, 'events' => EventResource::collection($events)->resolve()];
    }
}
