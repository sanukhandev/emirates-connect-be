<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MobileTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\BusinessMediaController;
use App\Http\Controllers\Api\V1\BusinessMemberController;
use App\Http\Controllers\Api\V1\BusinessPostController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\MyBusinessController;
use App\Http\Controllers\Api\V1\MyPostController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostMediaController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileMediaController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserPostController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'service' => 'emirates-connect-api',
            ],
        ]);
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class)->middleware('throttle:auth-login');
        Route::post('/mobile/token', MobileTokenController::class)->middleware('throttle:auth-login');
        Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:auth-forgot-password');
        Route::post('/reset-password', ResetPasswordController::class);
        Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');

        Route::middleware(['auth:sanctum', 'active.account'])->group(function (): void {
            Route::post('/logout', LogoutController::class);
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:auth-email-verification');
        });
    });

    Route::middleware(['auth:sanctum', 'active.account'])->group(function (): void {
        Route::get('/me', [AccountController::class, 'show']);
        Route::patch('/me', [AccountController::class, 'update']);
        Route::get('/me/posts', [MyPostController::class, 'index']);
        Route::get('/me/profile', [ProfileController::class, 'show']);
        Route::patch('/me/profile', [ProfileController::class, 'update']);
        Route::post('/me/onboarding/complete', [OnboardingController::class, 'complete']);
        Route::post('/me/profile/avatar', [ProfileMediaController::class, 'uploadAvatar']);
        Route::delete('/me/profile/avatar', [ProfileMediaController::class, 'deleteAvatar']);
        Route::post('/me/profile/cover-image', [ProfileMediaController::class, 'uploadCover']);
        Route::delete('/me/profile/cover-image', [ProfileMediaController::class, 'deleteCover']);
    });

    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/users/{user}/posts', [UserPostController::class, 'index']);
    Route::get('/businesses/{business:slug}', [BusinessController::class, 'show']);
    Route::get('/businesses/{business:slug}/posts', [BusinessPostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);
    Route::get('/meta/industries', [MetaController::class, 'industries']);
    Route::get('/meta/emirates', [MetaController::class, 'emirates']);

    Route::middleware(['auth:sanctum', 'active.account'])->group(function (): void {
        Route::post('/businesses', [BusinessController::class, 'store']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::patch('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
        Route::post('/posts/{post}/media', [PostMediaController::class, 'store']);
        Route::delete('/posts/{post}/media/{media}', [PostMediaController::class, 'destroy']);
        Route::get('/me/businesses', [MyBusinessController::class, 'index']);
        Route::patch('/businesses/{business:slug}', [BusinessController::class, 'update']);
        Route::delete('/businesses/{business:slug}', [BusinessController::class, 'destroy']);

        Route::get('/businesses/{business:slug}/members', [BusinessMemberController::class, 'index']);
        Route::post('/businesses/{business:slug}/members', [BusinessMemberController::class, 'store']);
        Route::patch('/businesses/{business:slug}/members/{member}', [BusinessMemberController::class, 'update']);
        Route::delete('/businesses/{business:slug}/members/{member}', [BusinessMemberController::class, 'destroy']);

        Route::post('/businesses/{business:slug}/logo', [BusinessMediaController::class, 'uploadLogo']);
        Route::delete('/businesses/{business:slug}/logo', [BusinessMediaController::class, 'deleteLogo']);
        Route::post('/businesses/{business:slug}/cover-image', [BusinessMediaController::class, 'uploadCover']);
        Route::delete('/businesses/{business:slug}/cover-image', [BusinessMediaController::class, 'deleteCover']);
    });
});
