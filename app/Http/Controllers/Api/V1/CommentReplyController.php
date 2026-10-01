<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\CreateReplyRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;

class CommentReplyController extends Controller
{
    public function store(CreateReplyRequest $request, Comment $comment, CommentService $service): JsonResponse
    {
        $this->authorize('reply', $comment);
        $reply = $service->create($request->user(), $comment->post, $request->validated(), $comment);

        return CommentResource::make($reply)->response()->setStatusCode(201);
    }
}
