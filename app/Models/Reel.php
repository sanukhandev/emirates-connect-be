<?php

namespace App\Models;

use App\Enums\ReelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'author_type', 'author_id', 'created_by_user_id', 'caption', 'status',
        'duration_seconds', 'width', 'height', 'source_disk', 'source_path',
        'playback_disk', 'playback_path', 'thumbnail_disk', 'thumbnail_path',
        'processing_error', 'published_at',
    ];

    protected $hidden = [
        'source_disk', 'source_path', 'playback_disk', 'playback_path',
        'thumbnail_disk', 'thumbnail_path', 'processing_error', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReelStatus::class,
            'published_at' => 'datetime',
            'duration_seconds' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }
}
