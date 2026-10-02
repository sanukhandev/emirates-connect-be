<?php

namespace App\Events;

use App\Models\Reaction;

class ReactionCreated
{
    public function __construct(public Reaction $reaction) {}
}
