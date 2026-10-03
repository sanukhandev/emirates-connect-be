<?php

namespace App\Models;

use App\Enums\ModerationAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $fillable = [
        'reporter_user_id', 'target_type', 'target_id', 'reason', 'details',
        'status', 'reviewed_by_user_id', 'reviewed_at', 'resolution', 'moderation_action', 'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'moderation_action' => ModerationAction::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ModerationAuditLog::class);
    }
}
