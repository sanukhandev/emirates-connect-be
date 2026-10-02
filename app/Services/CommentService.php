<?php

namespace App\Services;

use App\Events\CommentCreated;
use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\ReactionSummary;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public function create(User $actor, Post $post, array $data, ?Comment $parent = null): Comment
    {
        if (! app(PostVisibility::class)->isPublic($post)) {
            abort(404);
        }

        if ($parent !== null && ($parent->post_id !== $post->id || $parent->parent_id !== null || $parent->trashed())) {
            throw ValidationException::withMessages(['parent_id' => 'Replies must target a visible top-level comment.']);
        }

        $author = $this->resolveAuthor($actor, $data);
        $comment = new Comment([
            'body' => $data['body'],
            'created_by' => $actor->id,
        ]);
        $comment->post()->associate($post);
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
}
