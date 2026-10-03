<?php

namespace App\Enums;

enum ModerationAction: string
{
    case NONE = 'none';
    case CONTENT_REMOVED = 'content_removed';
    case ACCOUNT_SUSPENDED = 'account_suspended';
    case BUSINESS_SUSPENDED = 'business_suspended';
}
