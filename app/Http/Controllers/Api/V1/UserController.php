<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function show(Request $request, User $user, FollowService $service)
    {
        if ($user->account_status?->blocksAccess()) {
            return response()->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $user->profile()->firstOrCreate(['user_id' => $user->id], ['display_name' => $user->name]);

        return PublicUserResource::make($service->decorateUser($user->load('profile'), $request->user()));
    }
}
