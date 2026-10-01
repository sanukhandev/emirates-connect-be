<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\CreatePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use App\Services\PostVisibility;
use App\Support\ReactionSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function store(CreatePostRequest $request, PostService $service): JsonResponse
    {
        $post = $service->create($request->user(), $request->validated(), $request->file('media', []));

        return PostResource::make(ReactionSummary::load($post))->response()->setStatusCode(201);
    }

    public function show(Request $request, Post $post, PostVisibility $visibility): JsonResponse
    {
        $post = ReactionSummary::load($post->load(['author', 'media'])->loadMorph('author', [User::class => ['profile']]));
        if ($visibility->isPublic($post)) {
            return PostResource::make($post)->response()->setStatusCode(200);
        }

        abort_unless($request->user() && $request->user()->can('view', $post), 404);

        return PostResource::make($post)->response()->setStatusCode(200);
    }

    public function update(UpdatePostRequest $request, Post $post, PostService $service): JsonResponse
    {
        $this->authorize('update', $post);
        $post = $service->update($post, $request->validated());

        return PostResource::make(ReactionSummary::load($post))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, Post $post): Response
    {
        $this->authorize('delete', $post);
        $post->delete();

        return response()->noContent();
    }
}
