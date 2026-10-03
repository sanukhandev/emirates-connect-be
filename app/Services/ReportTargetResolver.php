<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReportTargetResolver
{
    public const TYPES = ['user', 'business', 'post', 'comment', 'reel'];

    public function resolve(string $type, int $id): Model
    {
        $target = match ($type) {
            'user' => User::query()->whereKey($id)->where('account_status', 'active')->first(),
            'business' => Business::query()->whereKey($id)->where('status', 'active')->first(),
            'post' => Post::query()->find($id),
            'comment' => Comment::query()->with('post')->find($id),
            'reel' => Reel::query()->find($id),
            default => null,
        };

        abort_unless($target, 404);

        if ($target instanceof Post) {
            abort_unless((new PostVisibility)->isPublic($target), 404);
        } elseif ($target instanceof Comment) {
            abort_unless($target->post && (new PostVisibility)->isPublic($target->post), 404);
        } elseif ($target instanceof Reel) {
            abort_unless((new ReelVisibility)->isPublic($target), 404);
        }

        return $target;
    }
}
