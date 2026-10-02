<?php

namespace App\Enums;

enum ReelStatus: string
{
    case UPLOADING = 'uploading';
    case PROCESSING = 'processing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
}
