<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function update(UpdateCommentRequest $request, Comment $comment, CommentService $service): CommentResource
    {
        $this->authorize('update', $comment);

        return CommentResource::make($service->update($comment, $request->validated('body')));
    }

    public function destroy(Comment $comment): Response
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return response()->noContent();
    }
}
