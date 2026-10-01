<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ProfileResource::make($this->profile($request))->response()->setStatusCode(200);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $profile->update($request->validated());

        return ProfileResource::make($profile->refresh())->response()->setStatusCode(200);
    }

    private function profile(Request $request)
    {
        $user = $request->user();

        return $user->profile()->firstOrCreate(['user_id' => $user->id], ['display_name' => $user->name]);
    }
}
