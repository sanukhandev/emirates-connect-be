<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserFollowController extends Controller
{
    public function update(Request $request, User $user, FollowService $service): PublicUserResource
    {
        $service->follow($request->user(), $user);

        return PublicUserResource::make($service->decorateUser($user->load('profile'), $request->user()));
    }

    public function destroy(Request $request, User $user, FollowService $service): Response
    {
        $service->unfollow($request->user(), $user);

        return response()->noContent();
    }
}
