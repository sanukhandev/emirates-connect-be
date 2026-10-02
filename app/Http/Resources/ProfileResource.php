<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'display_name' => $this->display_name,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'job_title' => $this->job_title,
            'company_name' => $this->company_name,
            'industry' => $this->industry?->value,
            'emirate' => $this->emirate?->value,
            'website_url' => $this->website_url,
            'linkedin_url' => $this->linkedin_url,
            'avatar_url' => $this->avatar_path ? Storage::url($this->avatar_path) : null,
            'cover_image_url' => $this->cover_image_path ? Storage::url($this->cover_image_path) : null,
            'onboarding_completed' => $this->onboarding_completed_at !== null,
            'is_verified' => $this->verification_status?->value === 'approved',
        ];
    }
}
