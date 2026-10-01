<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\PostStatus;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class FeedService
{
    public function paginate(int $perPage = 20, ?string $cursor = null): CursorPaginator
    {
        if ($cursor !== null && ! $this->isValidCursor($cursor)) {
            throw ValidationException::withMessages(['cursor' => 'The cursor is invalid.']);
        }

        return Post::query()
            ->where('status', PostStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where(function ($query): void {
                $query
                    ->whereHasMorph('author', [User::class], function ($authorQuery): void {
                        $authorQuery->where('account_status', UserStatus::ACTIVE);
                    })
                    ->orWhereHasMorph('author', [Business::class], function ($authorQuery): void {
                        $authorQuery->where('status', BusinessStatus::ACTIVE);
                    });
            })
            ->with([
                'author' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([User::class => ['profile']]);
                },
                'media',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    private function isValidCursor(string $encoded): bool
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($decoded === false) {
            return false;
        }

        $parameters = json_decode($decoded, true);

        return is_array($parameters)
            && array_key_exists('published_at', $parameters)
            && array_key_exists('id', $parameters)
            && array_key_exists('_pointsToNextItems', $parameters)
            && is_bool($parameters['_pointsToNextItems']);
    }
}
