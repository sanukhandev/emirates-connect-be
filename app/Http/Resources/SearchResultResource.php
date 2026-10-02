<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
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
                'avatar_url' => $this->avatar_path ? Storage::url($this->avatar_path) : null,
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
            'logo_url' => $this->logo_path ? Storage::url($this->logo_path) : null,
            'industry' => $this->industry,
            'emirate' => $this->emirate,
            'is_verified' => (bool) $this->is_verified,
        ];
    }
}
