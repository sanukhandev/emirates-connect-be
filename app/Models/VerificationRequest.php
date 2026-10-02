<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VerificationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type', 'subject_id', 'status', 'submitted_by_user_id',
        'reviewed_by_user_id', 'reviewed_at', 'submitted_at', 'rejection_reason', 'data',
    ];

    protected function casts(): array
    {
        return ['status' => VerificationStatus::class, 'reviewed_at' => 'datetime', 'submitted_at' => 'datetime', 'data' => 'array'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(VerificationAuditLog::class);
    }
}
