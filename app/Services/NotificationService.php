<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Enums\NotificationType;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use App\Models\VerificationRequest;

class NotificationService
{
    public function followed(User $actor, User $recipient, string $dedupeKey): void
    {
        $this->create(NotificationType::FOLLOWED, $recipient, $actor, $actor, ['user_id' => $actor->id], $dedupeKey);
    }

    public function commentCreated(Comment $comment): void
    {
        $post = $comment->loadMissing('post.author')->post;
        if ($comment->parent_id !== null) {
            return;
        }
        if ($post?->author instanceof User) {
            $recipient = $post->author;
            $this->create(NotificationType::POST_COMMENTED, $recipient, $comment->creator, $post, ['post_id' => $post->id, 'comment_id' => $comment->id], "comment:{$comment->id}");
        }
    }

    public function replyCreated(Comment $reply): void
    {
        $parent = $reply->loadMissing('parent.author')->parent;
        if ($parent?->author instanceof User) {
            $this->create(NotificationType::COMMENT_REPLIED, $parent->author, $reply->creator, $reply->post, ['post_id' => $reply->post_id, 'comment_id' => $reply->id, 'parent_comment_id' => $parent->id], "reply:{$reply->id}");
        }
    }

    public function reactionCreated(Reaction $reaction): void
    {
        $target = $reaction->loadMissing('reactable')->reactable;
        $recipient = match (true) {
            $target instanceof Post => $target->loadMissing('author')->author,
            $target instanceof Comment => $target->loadMissing('author')->author,
            default => null,
        };
        if (! $recipient instanceof User) {
            return;
        }
        $type = $target instanceof Post ? NotificationType::POST_REACTED : NotificationType::COMMENT_REACTED;
        $data = $target instanceof Post
            ? ['post_id' => $target->id]
            : ['post_id' => $target->post_id, 'comment_id' => $target->id];
        $this->create($type, $recipient, $reaction->user, $target, $data, "reaction:{$reaction->id}");
    }

    public function verificationReviewed(VerificationRequest $verification): void
    {
        $subject = $verification->loadMissing('subject')->subject;
        $type = $verification->status === VerificationStatus::APPROVED
            ? NotificationType::VERIFICATION_APPROVED
            : NotificationType::VERIFICATION_REJECTED;
        $data = ['subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'status' => $verification->status->value];
        if ($subject instanceof User) {
            $this->create($type, $subject, null, $subject, $data, "verification:{$verification->id}:{$verification->status->value}");
        }
        if ($subject instanceof Business) {
            $subject->members()->where('role', BusinessRole::OWNER)->get()->each(fn ($member) => $this->create($type, $member->user, null, $subject, $data, "verification:{$verification->id}:{$verification->status->value}:{$member->user_id}"));
        }
    }

    private function create(NotificationType $type, User $recipient, ?User $actor, object $subject, array $data, string $dedupeKey): void
    {
        if ($actor?->is($recipient)) {
            return;
        }
        Notification::firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'recipient_user_id' => $recipient->id,
                'type' => $type,
                'actor_type' => $actor?->getMorphClass(),
                'actor_id' => $actor?->getKey(),
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'data' => $data,
            ],
        );
    }
}
