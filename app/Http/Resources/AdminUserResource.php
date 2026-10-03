<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->profile?->display_name,
            'email' => $this->email,
            'account_status' => $this->account_status?->value,
            'is_verified' => $this->profile?->verification_status?->value === 'approved',
            'is_system_admin' => (bool) $this->is_system_admin,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'counts' => [
                'posts' => $this->posts_count ?? null,
                'reels' => $this->reels_count ?? null,
            ],
        ];
    }
}
