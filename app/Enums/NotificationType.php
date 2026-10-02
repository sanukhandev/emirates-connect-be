<?php

namespace App\Enums;

enum NotificationType: string
{
    case FOLLOWED = 'followed';
    case POST_COMMENTED = 'post_commented';
    case COMMENT_REPLIED = 'comment_replied';
    case POST_REACTED = 'post_reacted';
    case COMMENT_REACTED = 'comment_reacted';
    case VERIFICATION_APPROVED = 'verification_approved';
    case VERIFICATION_REJECTED = 'verification_rejected';
}
