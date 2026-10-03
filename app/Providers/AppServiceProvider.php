<?php

namespace App\Providers;

use App\Events\CommentCreated;
use App\Events\ReactionCreated;
use App\Events\UserFollowed;
use App\Events\VerificationReviewed;
use App\Listeners\CreateNotification;
use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap(['user' => User::class, 'business' => Business::class, 'post' => Post::class, 'comment' => Comment::class, 'reel' => Reel::class]);
        Event::listen(UserFollowed::class, [CreateNotification::class, 'follow']);
        Event::listen(CommentCreated::class, [CreateNotification::class, 'comment']);
        Event::listen(ReactionCreated::class, [CreateNotification::class, 'reaction']);
        Event::listen(VerificationReviewed::class, [CreateNotification::class, 'verification']);

        RateLimiter::for('auth-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->identityKey($request));
        });

        RateLimiter::for('auth-forgot-password', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->identityKey($request));
        });

        RateLimiter::for('auth-email-verification', function (Request $request): Limit {
            return Limit::perMinute(3)->by(($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('search', function (Request $request): Limit {
            return Limit::perMinute(60)->by(($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('reel-mutations', function (Request $request): Limit {
            return Limit::perHour(20)->by(($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('reports', function (Request $request): Limit {
            return Limit::perHour(10)->by(($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip());
        });
    }

    private function identityKey(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email'))).'|'.$request->ip();
    }
}
