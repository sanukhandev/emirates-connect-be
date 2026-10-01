<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetReactionRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\ReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PostReactionController extends Controller
{
    public function update(SetReactionRequest $request, Post $post, ReactionService $service): JsonResponse
    {
        return PostResource::make($service->set($request->user(), $post, ReactionType::from($request->validated('type'))))
            ->response()->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Request $request, Post $post, ReactionService $service): Response
    {
        $service->remove($request->user(), $post);

        return response()->noContent();
    }
}
