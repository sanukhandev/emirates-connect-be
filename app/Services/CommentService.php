<?php

namespace App\Services;

use App\Events\CommentCreated;
use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reel;
use App\Models\User;
use App\Support\ReactionSummary;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public function create(User $actor, Post|Reel $target, array $data, ?Comment $parent = null): Comment
    {
        if (! $this->isPublic($target)) {
            abort(404);
        }

        if ($parent !== null && (! $this->belongsTo($parent, $target) || $parent->parent_id !== null || $parent->trashed())) {
            throw ValidationException::withMessages(['parent_id' => 'Replies must target a visible top-level comment.']);
        }

        $author = $this->resolveAuthor($actor, $data);
        $comment = new Comment([
            'body' => $data['body'],
            'created_by' => $actor->id,
        ]);
        $target instanceof Post ? $comment->post()->associate($target) : $comment->reel()->associate($target);
        $comment->parent()->associate($parent);
        $comment->author()->associate($author);
        $comment->save();
        event(new CommentCreated($comment));

        return $this->load($comment);
    }

    public function update(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);

        return $this->load($comment->refresh());
    }

    private function resolveAuthor(User $actor, array $data): User|Business
    {
        if ($data['author_type'] === 'user') {
            return $actor;
        }

        $business = Business::findOrFail((int) $data['business_id']);
        Gate::forUser($actor)->authorize('publish', $business);

        return $business;
    }

    private function load(Comment $comment): Comment
    {
        $comment->load(['author', 'replies.author'])->loadMorph('author', [User::class => ['profile']]);
        ReactionSummary::load($comment);
        $comment->replies->each(function (Comment $reply): void {
            $reply->loadMorph('author', [User::class => ['profile']]);
            ReactionSummary::load($reply);
        });

        return $comment;
    }

    private function isPublic(Post|Reel $target): bool
    {
        return $target instanceof Post
            ? app(PostVisibility::class)->isPublic($target)
            : app(ReelVisibility::class)->isPublic($target);
    }

    private function belongsTo(Comment $comment, Post|Reel $target): bool
    {
        return $target instanceof Post ? $comment->post_id === $target->id : $comment->reel_id === $target->id;
    }
}
