<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function update(UpdateAccountRequest $request): UserResource
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

        return UserResource::make($user->refresh());
    }
}
