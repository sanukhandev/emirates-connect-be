<?php

namespace App\Events;

use App\Models\VerificationRequest;

class VerificationReviewed
{
    public function __construct(public VerificationRequest $verification) {}
}
