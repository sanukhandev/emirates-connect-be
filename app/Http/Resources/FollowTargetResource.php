<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof User) {
            return [
                'type' => 'user', 'id' => $this->id, 'name' => $this->name,
                'display_name' => $this->profile?->display_name,
                'headline' => $this->profile?->headline,
                'avatar_url' => MediaUrl::for($request, 'public', $this->profile?->avatar_path),
            ];
        }

        if ($this->resource instanceof Business) {
            return [
                'type' => 'business', 'id' => $this->id, 'name' => $this->name,
                'slug' => $this->slug,
                'logo_url' => MediaUrl::for($request, 'public', $this->logo_path),
            ];
        }

        return [];
    }
}
