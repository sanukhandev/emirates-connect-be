<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\CreateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostVisibility;
use App\Support\ReactionSummary;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;

class PostCommentController extends Controller
{
    public function index(Post $post, PostVisibility $visibility): mixed
    {
        abort_unless($visibility->isPublic($post), 404);

        $comments = $post->comments()
            ->whereNull('parent_id')
            ->with([
                'author' => fn (MorphTo $morphTo) => $morphTo->morphWith([User::class => ['profile']]),
                'replies.author' => fn (MorphTo $morphTo) => $morphTo->morphWith([User::class => ['profile']]),
                'replies' => function ($query): void {
                    ReactionSummary::apply($query);
                },
            ])
            ->withCount(['replies' => fn ($query) => $query->whereNull('deleted_at')])
            ->oldest('created_at')->oldest('id');

        ReactionSummary::apply($comments);
        $comments = $comments->paginate(20);

        return CommentResource::collection($comments);
    }

    public function store(CreateCommentRequest $request, Post $post, CommentService $service): JsonResponse
    {
        $comment = $service->create($request->user(), $post, $request->validated());

        return CommentResource::make($comment)->response()->setStatusCode(201);
    }
}
