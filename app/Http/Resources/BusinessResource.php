<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = null;
        if ($request->user()) {
            $member = $this->relationLoaded('members')
                ? $this->members->firstWhere('user_id', $request->user()->id)
                : $this->members()->where('user_id', $request->user()->id)->first();
            $role = $member?->role?->value;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'industry' => $this->industry?->value,
            'emirate' => $this->emirate?->value,
            'website_url' => $this->website_url,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo_url' => $this->logo_path ? Storage::url($this->logo_path) : null,
            'cover_image_url' => $this->cover_image_path ? Storage::url($this->cover_image_path) : null,
            'status' => $this->status?->value,
            'current_user_role' => $role,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
