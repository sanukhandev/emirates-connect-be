<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('owner') ? $this->owner?->user : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status?->value,
            'is_verified' => $this->verification_status?->value === 'approved',
            'owner' => $owner ? ['id' => $owner->id, 'display_name' => $owner->profile?->display_name ?: $owner->name] : null,
            'member_count' => $this->members_count ?? null,
            'counts' => [
                'posts' => $this->posts_count ?? null,
                'reels' => $this->reels_count ?? null,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
