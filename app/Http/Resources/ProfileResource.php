<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'avatar_url' => MediaUrl::for($request, 'public', $this->avatar_path),
            'cover_image_url' => MediaUrl::for($request, 'public', $this->cover_image_path),
            'onboarding_completed' => $this->onboarding_completed_at !== null,
            'is_verified' => $this->verification_status?->value === 'approved',
        ];
    }
}
