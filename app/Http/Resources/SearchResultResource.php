<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->type === 'user') {
            return [
                'type' => 'user',
                'id' => (int) $this->id,
                'display_name' => $this->display_name,
                'headline' => $this->headline,
                'avatar_url' => MediaUrl::for($request, 'public', $this->avatar_path),
                'industry' => $this->industry,
                'emirate' => $this->emirate,
                'is_verified' => (bool) $this->is_verified,
            ];
        }

        return [
            'type' => 'business',
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ? Str::limit($this->description, 240) : null,
            'logo_url' => MediaUrl::for($request, 'public', $this->logo_path),
            'industry' => $this->industry,
            'emirate' => $this->emirate,
            'is_verified' => (bool) $this->is_verified,
        ];
    }
}
