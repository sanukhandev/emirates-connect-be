<?php

namespace App\Models;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\Emirate;
use App\Enums\Industry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'tagline', 'description', 'industry', 'emirate',
        'website_url', 'email', 'phone', 'logo_path', 'cover_image_path',
        'status', 'created_by',
    ];

    protected $hidden = ['logo_path', 'cover_image_path', 'created_by'];

    protected function casts(): array
    {
        return [
            'industry' => Industry::class,
            'emirate' => Emirate::class,
            'status' => BusinessStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'business_members')->withPivot('role')->withTimestamps();
    }

    public function owner(): HasOne
    {
        return $this->hasOne(BusinessMember::class)->where('role', BusinessRole::OWNER);
    }
}
