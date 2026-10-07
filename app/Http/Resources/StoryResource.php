<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'media_url' => MediaUrl::for($request, 'public', $this->media_path),
            'media_type' => $this->media_type,
            'expires_at' => $this->expires_at?->toISOString(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->profile?->display_name ?: $this->user?->name,
                'avatar_url' => MediaUrl::for($request, 'public', $this->user?->profile?->avatar_path),
                'is_verified' => $this->user?->profile?->verification_status?->value === 'approved',
            ],
        ];
    }
}
