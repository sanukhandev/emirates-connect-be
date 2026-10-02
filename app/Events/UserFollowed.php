<?php

namespace App\Events;

use App\Models\Follow;
use App\Models\User;

class UserFollowed
{
    public function __construct(public User $actor, public User $recipient, public Follow $follow) {}
}
