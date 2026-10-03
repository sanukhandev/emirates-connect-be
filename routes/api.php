<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\AuditController as AdminAuditController;
use App\Http\Controllers\Api\V1\Admin\BusinessController as AdminBusinessController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MobileTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\BusinessFollowController;
use App\Http\Controllers\Api\V1\BusinessFollowerController;
use App\Http\Controllers\Api\V1\BusinessMediaController;
use App\Http\Controllers\Api\V1\BusinessMemberController;
use App\Http\Controllers\Api\V1\BusinessPostController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\CommentReactionController;
use App\Http\Controllers\Api\V1\CommentReplyController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\MyBusinessController;
use App\Http\Controllers\Api\V1\MyPostController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PostCommentController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostMediaController;
use App\Http\Controllers\Api\V1\PostReactionController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileMediaController;
use App\Http\Controllers\Api\V1\ReelController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserFollowController;
use App\Http\Controllers\Api\V1\UserNetworkController;
use App\Http\Controllers\Api\V1\UserPostController;
use App\Http\Controllers\Api\V1\VerificationController;
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
        Route::post('/register', RegisterController::class)->middleware('throttle:auth-register');
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
        Route::get('/me/reels', [ReelController::class, 'me']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:reports');
        Route::get('/me/reports', [ReportController::class, 'mine']);
        Route::get('/feed', [FeedController::class, 'index']);
        Route::get('/me/profile', [ProfileController::class, 'show']);
        Route::patch('/me/profile', [ProfileController::class, 'update']);
        Route::post('/me/onboarding/complete', [OnboardingController::class, 'complete']);
        Route::get('/verification/user', [VerificationController::class, 'userStatus']);
        Route::post('/verification/user', [VerificationController::class, 'submitUser'])->middleware('throttle:verification-submissions');
        Route::get('/verification/business/{business:slug}', [VerificationController::class, 'businessStatus']);
        Route::post('/verification/business/{business:slug}', [VerificationController::class, 'submitBusiness'])->middleware('throttle:verification-submissions');
        Route::post('/me/profile/avatar', [ProfileMediaController::class, 'uploadAvatar']);
        Route::delete('/me/profile/avatar', [ProfileMediaController::class, 'deleteAvatar']);
        Route::post('/me/profile/cover-image', [ProfileMediaController::class, 'uploadCover']);
        Route::delete('/me/profile/cover-image', [ProfileMediaController::class, 'deleteCover']);
    });

    Route::middleware(['auth:sanctum', 'active.account', 'system.admin'])->prefix('admin/reports')->group(function (): void {
        Route::get('/', [AdminReportController::class, 'index']);
        Route::get('/{report}', [AdminReportController::class, 'show']);
        Route::patch('/{report}', [AdminReportController::class, 'update'])->middleware('throttle:admin-mutations');
    });

    Route::middleware(['auth:sanctum', 'active.account', 'system.admin'])->prefix('admin/verifications')->group(function (): void {
        Route::get('/', [AdminVerificationController::class, 'index']);
        Route::get('/audit', [AdminVerificationController::class, 'audit']);
        Route::get('/{verification}', [AdminVerificationController::class, 'show']);
        Route::post('/{verification}/approve', [AdminVerificationController::class, 'approve'])->middleware('throttle:admin-mutations');
        Route::post('/{verification}/reject', [AdminVerificationController::class, 'reject'])->middleware('throttle:admin-mutations');
        Route::get('/{verification}/documents/{document}', [AdminVerificationController::class, 'document']);
    });

    Route::middleware(['auth:sanctum', 'active.account', 'system.admin'])->prefix('admin')->group(function (): void {
        Route::get('/dashboard', AdminDashboardController::class);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->middleware('throttle:admin-mutations');
        Route::get('/businesses', [AdminBusinessController::class, 'index']);
        Route::get('/businesses/{business}', [AdminBusinessController::class, 'show']);
        Route::post('/businesses/{business}/suspend', [AdminBusinessController::class, 'suspend'])->middleware('throttle:admin-mutations');
        Route::get('/moderation/audit', [AdminAuditController::class, 'moderation']);
    });

    Route::middleware(['web', 'auth:sanctum', 'active.account', 'system.admin', 'signed', 'throttle:admin-documents'])->get('/admin/verifications/{verification}/documents/{document}/download', [AdminVerificationController::class, 'download'])->name('verification.document.download');

    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/users/{user}/posts', [UserPostController::class, 'index']);
    Route::get('/users/{user}/reels', [ReelController::class, 'user']);
    Route::get('/users/{user}/followers', [UserNetworkController::class, 'followers']);
    Route::get('/users/{user}/following', [UserNetworkController::class, 'following']);
    Route::get('/businesses/{business:slug}', [BusinessController::class, 'show']);
    Route::get('/businesses/{business:slug}/posts', [BusinessPostController::class, 'index']);
    Route::get('/businesses/{business:slug}/reels', [ReelController::class, 'business']);
    Route::get('/businesses/{business:slug}/followers', [BusinessFollowerController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);
    Route::get('/reels', [ReelController::class, 'index']);
    Route::get('/reels/{reel}', [ReelController::class, 'show']);
    Route::get('/posts/{post}/comments', [PostCommentController::class, 'index']);
    Route::get('/meta/industries', [MetaController::class, 'industries']);
    Route::get('/meta/emirates', [MetaController::class, 'emirates']);
    Route::middleware('throttle:search')->group(function (): void {
        Route::get('/search', [SearchController::class, 'index']);
        Route::get('/search/users', [SearchController::class, 'users']);
        Route::get('/search/businesses', [SearchController::class, 'businesses']);
    });

    Route::middleware(['auth:sanctum', 'active.account'])->group(function (): void {
        Route::post('/businesses', [BusinessController::class, 'store']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::middleware('throttle:reel-mutations')->group(function (): void {
            Route::post('/reels', [ReelController::class, 'store']);
            Route::post('/reels/{reel}/video', [ReelController::class, 'upload']);
            Route::patch('/reels/{reel}', [ReelController::class, 'update']);
            Route::delete('/reels/{reel}', [ReelController::class, 'destroy']);
        });
        Route::post('/posts/{post}/comments', [PostCommentController::class, 'store']);
        Route::post('/comments/{comment}/replies', [CommentReplyController::class, 'store']);
        Route::patch('/comments/{comment}', [CommentController::class, 'update']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
        Route::patch('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
        Route::post('/posts/{post}/media', [PostMediaController::class, 'store']);
        Route::delete('/posts/{post}/media/{media}', [PostMediaController::class, 'destroy']);
        Route::put('/posts/{post}/reaction', [PostReactionController::class, 'update']);
        Route::delete('/posts/{post}/reaction', [PostReactionController::class, 'destroy']);
        Route::put('/comments/{comment}/reaction', [CommentReactionController::class, 'update']);
        Route::delete('/comments/{comment}/reaction', [CommentReactionController::class, 'destroy']);
        Route::put('/users/{user}/follow', [UserFollowController::class, 'update']);
        Route::delete('/users/{user}/follow', [UserFollowController::class, 'destroy']);
        Route::put('/businesses/{business:slug}/follow', [BusinessFollowController::class, 'update']);
        Route::delete('/businesses/{business:slug}/follow', [BusinessFollowController::class, 'destroy']);
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
