<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\FollowService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BusinessFollowController extends Controller
{
    public function update(Request $request, Business $business, FollowService $service): BusinessResource
    {
        $service->follow($request->user(), $business);

        return BusinessResource::make($service->decorateBusiness($business, $request->user()));
    }

    public function destroy(Request $request, Business $business, FollowService $service): Response
    {
        $service->unfollow($request->user(), $business);

        return response()->noContent();
    }
}
