<?php

namespace App\Enums;

enum ReportReason: string
{
    case SPAM = 'spam';
    case HARASSMENT = 'harassment';
    case HATE_OR_ABUSE = 'hate_or_abuse';
    case MISINFORMATION = 'misinformation';
    case IMPERSONATION = 'impersonation';
    case INAPPROPRIATE_CONTENT = 'inappropriate_content';
    case FRAUD_OR_SCAM = 'fraud_or_scam';
    case PRIVACY_VIOLATION = 'privacy_violation';
    case OTHER = 'other';
}
