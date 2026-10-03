<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\ModerationAction;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\Comment;
use App\Models\ModerationAuditLog;
use App\Models\Post;
use App\Models\Reel;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private readonly ReportTargetResolver $targets) {}

    public function create(User $reporter, array $data): Report
    {
        $target = $this->targets->resolve($data['target_type'], (int) $data['target_id']);
        abort_if($this->isSelfTarget($reporter, $target), 422, 'You cannot report your own content.');

        $dedupeKey = implode(':', [$reporter->id, $data['target_type'], $target->getKey()]);
        $exists = Report::query()->where('dedupe_key', $dedupeKey)->exists();
        abort_if($exists, 409, 'You already have an unresolved report for this target.');

        return Report::query()->create([
            'reporter_user_id' => $reporter->id,
            'target_type' => $data['target_type'],
            'target_id' => $target->getKey(),
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => ReportStatus::PENDING,
            'moderation_action' => ModerationAction::NONE,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    public function resolve(Report $report, User $admin, string $action, string $resolution): Report
    {
        return DB::transaction(function () use ($report, $admin, $action, $resolution): Report {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);
            abort_if(in_array($report->status, [ReportStatus::DISMISSED, ReportStatus::ACTIONED], true), 409, 'Report is already resolved.');

            if ($action === 'dismiss') {
                return $this->finish($report, $admin, ReportStatus::DISMISSED, ModerationAction::NONE, $resolution, 'report_dismissed');
            }

            $moderation = ModerationAction::from($action);
            $target = $report->target()->first();
            abort_unless($target, 404, 'Reported target is unavailable.');
            $this->apply($target, $moderation);

            return $this->finish($report, $admin, ReportStatus::ACTIONED, $moderation, $resolution, $this->auditAction($moderation));
        });
    }

    private function finish(Report $report, User $admin, ReportStatus $status, ModerationAction $action, string $resolution, string $auditAction): Report
    {
        $report->forceFill([
            'status' => $status, 'moderation_action' => $action, 'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(), 'resolution' => $resolution, 'dedupe_key' => null,
        ])->save();

        ModerationAuditLog::query()->create([
            'report_id' => $report->id, 'actor_user_id' => $admin->id, 'action' => $auditAction,
            'target_type' => $report->target_type, 'target_id' => $report->target_id,
            'metadata' => ['resolution' => $resolution], 'created_at' => now(),
        ]);

        return $report->fresh();
    }

    private function apply(Model $target, ModerationAction $action): void
    {
        $valid = match ($action) {
            ModerationAction::CONTENT_REMOVED => $target instanceof Post || $target instanceof Comment || $target instanceof Reel,
            ModerationAction::ACCOUNT_SUSPENDED => $target instanceof User,
            ModerationAction::BUSINESS_SUSPENDED => $target instanceof Business,
            default => false,
        };
        abort_unless($valid, 422, 'The moderation action is not valid for this target.');

        if ($action === ModerationAction::CONTENT_REMOVED) {
            $target->delete();
        } elseif ($action === ModerationAction::ACCOUNT_SUSPENDED) {
            $target->forceFill(['account_status' => UserStatus::SUSPENDED])->save();
        } else {
            $target->forceFill(['status' => BusinessStatus::SUSPENDED])->save();
        }
    }

    private function isSelfTarget(User $reporter, Model $target): bool
    {
        if ($target instanceof User) {
            return $target->is($reporter);
        }
        if ($target instanceof Post || $target instanceof Comment || $target instanceof Reel) {
            return (int) ($target->created_by ?? $target->created_by_user_id ?? 0) === $reporter->id
                || ($target->author_type ?? null) === 'user' && (int) ($target->author_id ?? 0) === $reporter->id;
        }

        return false;
    }

    private function auditAction(ModerationAction $action): string
    {
        return match ($action) {
            ModerationAction::CONTENT_REMOVED => 'content_removed',
            ModerationAction::ACCOUNT_SUSPENDED => 'user_suspended',
            ModerationAction::BUSINESS_SUSPENDED => 'business_suspended',
            default => 'report_reviewed',
        };
    }
}
