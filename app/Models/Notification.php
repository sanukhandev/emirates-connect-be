<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $fillable = [
        'recipient_user_id', 'type', 'actor_type', 'actor_id',
        'subject_type', 'subject_id', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return ['type' => NotificationType::class, 'data' => 'array', 'read_at' => 'datetime'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
