<?php

namespace App\Models;

use App\Enums\ReactionType;
use Database\Factories\ReactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    use HasFactory;

    protected static function newFactory(): ReactionFactory
    {
        return ReactionFactory::new();
    }

    protected $fillable = ['user_id', 'reactable_type', 'reactable_id', 'type'];

    protected function casts(): array
    {
        return ['type' => ReactionType::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}
