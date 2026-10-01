<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\UserResource;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request, FollowService $service): JsonResponse
    {
        $user = $request->user();
        $user->profile()->firstOrCreate(['user_id' => $user->id], ['display_name' => $user->name]);

        return UserResource::make($service->decorateUser($user->load('profile'), $user))->response()->setStatusCode(200);
    }

    public function update(UpdateAccountRequest $request, FollowService $service): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $user->email;

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        $user->profile()->firstOrCreate(['user_id' => $user->id], ['display_name' => $user->name]);
        $user->unsetRelation('follow_summary');

        return UserResource::make($service->decorateUser($user->refresh()->load('profile'), $user))->response()->setStatusCode(200);
    }
}
