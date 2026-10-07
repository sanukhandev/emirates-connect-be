<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReelAuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof User) {
            return [
                'type' => 'user', 'id' => $this->id,
                'display_name' => $this->profile?->display_name ?? $this->name,
                'headline' => $this->profile?->headline,
                'avatar_url' => $this->profile?->avatar_path ? Storage::disk('public')->url($this->profile->avatar_path) : null,
                'is_verified' => $this->profile?->verification_status?->value === 'approved',
            ];
        }

        if ($this->resource instanceof Business) {
            return [
                'type' => 'business', 'id' => $this->id, 'name' => $this->name,
                'slug' => $this->slug,
                'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
                'is_verified' => $this->verification_status?->value === 'approved',
            ];
        }

        return [];
    }
}
