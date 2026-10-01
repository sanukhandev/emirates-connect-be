<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\PostStatus;
use App\Models\Business;
use App\Models\Post;
use App\Models\User;

class PostVisibility
{
    public function isPublic(Post $post): bool
    {
        if ($post->status !== PostStatus::PUBLISHED || $post->deleted_at !== null || $post->published_at === null) {
            return false;
        }

        $author = $post->author;

        return ($author instanceof User && ! $author->account_status?->blocksAccess())
            || ($author instanceof Business && $author->status === BusinessStatus::ACTIVE);
    }
}
