<?php

namespace App\Enums;

enum ReactionType: string
{
    case LIKE = 'like';
    case CELEBRATE = 'celebrate';
    case SUPPORT = 'support';
    case INSIGHTFUL = 'insightful';
}
