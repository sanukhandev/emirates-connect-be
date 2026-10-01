<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeedRequest;
use App\Http\Resources\PostResource;
use App\Services\FeedService;

class FeedController extends Controller
{
    public function index(FeedRequest $request, FeedService $service)
    {
        return PostResource::collection($service->paginate(
            $request->integer('per_page', 20),
            $request->input('cursor'),
        ));
    }
}
