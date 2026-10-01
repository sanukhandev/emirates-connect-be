<?php

namespace App\Models;

use Database\Factories\PostMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedia extends Model
{
    use HasFactory;

    protected static function newFactory(): PostMediaFactory
    {
        return PostMediaFactory::new();
    }

    protected $table = 'post_media';

    protected $fillable = ['post_id', 'type', 'path', 'mime_type', 'size', 'width', 'height', 'sort_order'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
