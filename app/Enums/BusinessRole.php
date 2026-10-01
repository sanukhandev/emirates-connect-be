<?php

namespace App\Enums;

enum BusinessRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case EDITOR = 'editor';
}
