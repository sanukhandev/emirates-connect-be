<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\AddPostMediaRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostMediaController extends Controller
{
    public function store(AddPostMediaRequest $request, Post $post, PostService $service): JsonResponse
    {
        $this->authorize('addMedia', $post);
        $post = $service->addMedia($post, $request->file('media'));

        return PostResource::make($post)->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Post $post, int $media): JsonResponse
    {
        $this->authorize('deleteMedia', $post);
        $post = app(PostService::class)->deleteMedia($post, $media);

        return PostResource::make($post)->response()->setStatusCode(200);
    }
}
