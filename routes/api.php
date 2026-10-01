<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MobileTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileMediaController;
use App\Http\Controllers\Api\V1\UserController;
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
        Route::get('/me/profile', [ProfileController::class, 'show']);
        Route::patch('/me/profile', [ProfileController::class, 'update']);
        Route::post('/me/onboarding/complete', [OnboardingController::class, 'complete']);
        Route::post('/me/profile/avatar', [ProfileMediaController::class, 'uploadAvatar']);
        Route::delete('/me/profile/avatar', [ProfileMediaController::class, 'deleteAvatar']);
        Route::post('/me/profile/cover-image', [ProfileMediaController::class, 'uploadCover']);
        Route::delete('/me/profile/cover-image', [ProfileMediaController::class, 'deleteCover']);
    });

    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/meta/industries', [MetaController::class, 'industries']);
    Route::get('/meta/emirates', [MetaController::class, 'emirates']);
});
