<?php

namespace App\Events;

use App\Models\Comment;

class CommentCreated
{
    public function __construct(public Comment $comment) {}
}
