<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
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
        Relation::enforceMorphMap(['user' => User::class, 'business' => Business::class, 'post' => Post::class, 'comment' => Comment::class]);

        RateLimiter::for('auth-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->identityKey($request));
        });

        RateLimiter::for('auth-forgot-password', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->identityKey($request));
        });

        RateLimiter::for('auth-email-verification', function (Request $request): Limit {
            return Limit::perMinute(3)->by(($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip());
        });
    }

    private function identityKey(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email'))).'|'.$request->ip();
    }
}
