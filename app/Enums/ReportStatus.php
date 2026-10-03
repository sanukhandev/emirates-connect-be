<?php

namespace App\Enums;

enum ReportStatus: string
{
    case PENDING = 'pending';
    case REVIEWED = 'reviewed';
    case DISMISSED = 'dismissed';
    case ACTIONED = 'actioned';
}
