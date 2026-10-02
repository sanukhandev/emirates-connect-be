<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\ReelStatus;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReelVisibility
{
    public function publicQuery(): Builder
    {
        return Reel::query()
            ->where('status', ReelStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereHasMorph('author', [User::class], fn (Builder $author): Builder => $author->where('account_status', UserStatus::ACTIVE->value))
                    ->orWhereHasMorph('author', [Business::class], fn (Builder $author): Builder => $author->where('status', BusinessStatus::ACTIVE->value));
            });
    }

    public function isPublic(Reel $reel): bool
    {
        return $this->publicQuery()->whereKey($reel->getKey())->exists();
    }
}
