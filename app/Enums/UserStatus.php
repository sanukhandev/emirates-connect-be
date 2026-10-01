<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DISABLED = 'disabled';

    public function blocksAccess(): bool
    {
        return match ($this) {
            self::SUSPENDED, self::DISABLED => true,
            default => false,
        };
    }
}
