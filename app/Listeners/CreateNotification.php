<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Events\ReactionCreated;
use App\Events\UserFollowed;
use App\Events\VerificationReviewed;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(private readonly NotificationService $notifications)
    {
        $this->afterCommit = true;
        $this->onQueue('notifications');
    }

    public function follow(UserFollowed $event): void
    {
        $this->notifications->followed($event->actor, $event->recipient, "follow:{$event->follow->id}");
    }

    public function comment(CommentCreated $event): void
    {
        $event->comment->parent_id === null ? $this->notifications->commentCreated($event->comment) : $this->notifications->replyCreated($event->comment);
    }

    public function reaction(ReactionCreated $event): void
    {
        $this->notifications->reactionCreated($event->reaction);
    }

    public function verification(VerificationReviewed $event): void
    {
        $this->notifications->verificationReviewed($event->verification);
    }
}
