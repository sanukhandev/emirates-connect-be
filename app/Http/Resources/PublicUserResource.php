<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'profile' => ProfileResource::make($this->profile),
            'followers_count' => $this->when($this->relationLoaded('follow_summary'), fn () => $this->follow_summary['followers_count']),
            'following_count' => $this->when($this->relationLoaded('follow_summary'), fn () => $this->follow_summary['following_count']),
            'is_following' => $this->when($this->relationLoaded('follow_summary'), fn () => $this->follow_summary['is_following']),
            'is_verified' => $this->profile?->verification_status?->value === 'approved',
        ];
    }
}
