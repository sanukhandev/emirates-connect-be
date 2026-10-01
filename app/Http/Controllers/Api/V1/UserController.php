<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function show(User $user)
    {
        if ($user->account_status?->blocksAccess()) {
            return response()->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $user->profile()->firstOrCreate(['user_id' => $user->id], ['display_name' => $user->name]);

        return PublicUserResource::make($user->load('profile'));
    }
}
