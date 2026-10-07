<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'media_url' => $this->media_path ? Storage::url($this->media_path) : null,
            'media_type' => $this->media_type,
            'expires_at' => $this->expires_at?->toISOString(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->profile?->display_name ?: $this->user?->name,
                'avatar_url' => $this->user?->profile?->avatar_path ? Storage::url($this->user->profile->avatar_path) : null,
                'is_verified' => $this->user?->profile?->verification_status?->value === 'approved',
            ],
        ];
    }
}
