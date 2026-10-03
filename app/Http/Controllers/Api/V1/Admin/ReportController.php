<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateReportRequest;
use App\Http\Resources\AdminReportResource;
use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reel;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): mixed
    {
        $query = $this->query($request);
        foreach (['status' => ReportStatus::class, 'reason' => ReportReason::class] as $field => $enum) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }

        return AdminReportResource::collection($query->latest('created_at')->latest('id')->paginate(min($request->integer('per_page', 20), 50)));
    }

    public function show(Request $request, Report $report): AdminReportResource
    {
        return AdminReportResource::make($this->query($request)->with('auditLogs.actor.profile')->findOrFail($report->id));
    }

    public function update(ModerateReportRequest $request, Report $report, ReportService $service): AdminReportResource
    {
        return AdminReportResource::make($service->resolve($report, $request->user(), $request->string('action')->toString(), $request->string('resolution')->toString()));
    }

    private function query(Request $request)
    {
        return Report::query()->with([
            'reporter.profile', 'reviewer.profile',
            'target' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                User::class => ['profile'], Business::class => [], Post::class => ['author'],
                Comment::class => ['author', 'post'], Reel::class => ['author'],
            ]),
        ]);
    }
}
