<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\CreateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Reel;
use App\Models\User;
use App\Services\CommentService;
use App\Services\ReelVisibility;
use App\Support\ReactionSummary;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;

class ReelCommentController extends Controller
{
    public function index(Reel $reel, ReelVisibility $visibility): mixed
    {
        abort_unless($visibility->isPublic($reel), 404);

        $comments = $reel->comments()
            ->whereNull('parent_id')
            ->with([
                'author' => fn (MorphTo $morphTo) => $morphTo->morphWith([User::class => ['profile']]),
                'replies.author' => fn (MorphTo $morphTo) => $morphTo->morphWith([User::class => ['profile']]),
                'replies' => fn ($query) => ReactionSummary::apply($query),
            ])
            ->withCount(['replies' => fn ($query) => $query->whereNull('deleted_at')])
            ->oldest('created_at')->oldest('id');

        ReactionSummary::apply($comments);

        return CommentResource::collection($comments->paginate(20));
    }

    public function store(CreateCommentRequest $request, Reel $reel, CommentService $service): JsonResponse
    {
        return CommentResource::make($service->create($request->user(), $reel, $request->validated()))
            ->response()->setStatusCode(201);
    }
}
