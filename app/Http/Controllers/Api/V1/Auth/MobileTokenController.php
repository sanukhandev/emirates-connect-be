<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MobileTokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class MobileTokenController extends Controller
{
    public function __invoke(MobileTokenRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if ($user?->account_status instanceof UserStatus && $user->account_status->blocksAccess()) {
            return response()->json(['message' => 'Account is not active.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'data' => [
                'user' => UserResource::make($user),
                'token' => $user->createToken($request->string('device_name')->toString())->plainTextToken,
            ],
        ]);
    }
}
