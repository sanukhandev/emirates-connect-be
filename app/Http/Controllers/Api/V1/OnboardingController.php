<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\CompleteOnboardingRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class OnboardingController extends Controller
{
    public function complete(CompleteOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id], ['display_name' => $user->name]);
        $data = $request->validated();

        if ($profile->onboarding_completed_at === null) {
            $data['onboarding_completed_at'] = Carbon::now();
        }

        $profile->update($data);

        return ProfileResource::make($profile->refresh())->response()->setStatusCode(200);
    }
}
