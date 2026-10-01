<?php

namespace App\Models;

use App\Enums\Emirate;
use App\Enums\Industry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'display_name',
        'headline',
        'bio',
        'job_title',
        'company_name',
        'industry',
        'emirate',
        'website_url',
        'linkedin_url',
        'avatar_path',
        'cover_image_path',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'avatar_path',
        'cover_image_path',
    ];

    protected function casts(): array
    {
        return [
            'industry' => Industry::class,
            'emirate' => Emirate::class,
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
