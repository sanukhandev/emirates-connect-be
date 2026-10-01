<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetReactionRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Services\ReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommentReactionController extends Controller
{
    public function update(SetReactionRequest $request, Comment $comment, ReactionService $service): JsonResponse
    {
        return CommentResource::make($service->set($request->user(), $comment, ReactionType::from($request->validated('type'))))
            ->response()->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Request $request, Comment $comment, ReactionService $service): Response
    {
        $service->remove($request->user(), $comment);

        return response()->noContent();
    }
}
