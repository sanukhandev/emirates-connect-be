<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BusinessStatus;
use App\Enums\PostStatus;
use App\Enums\ReelStatus;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Post;
use App\Models\Reel;
use App\Models\Report;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'users' => ['total' => User::count(), 'suspended' => User::where('account_status', UserStatus::SUSPENDED)->count()],
            'businesses' => ['total' => Business::count(), 'suspended' => Business::where('status', BusinessStatus::SUSPENDED)->count()],
            'content' => [
                'published_posts' => Post::where('status', PostStatus::PUBLISHED)->whereNull('deleted_at')->count(),
                'published_reels' => Reel::where('status', ReelStatus::PUBLISHED)->whereNull('deleted_at')->count(),
            ],
            'queues' => [
                'pending_verifications' => VerificationRequest::where('status', VerificationStatus::PENDING)->count(),
                'pending_reports' => Report::where('status', ReportStatus::PENDING)->count(),
            ],
        ]]);
    }
}
