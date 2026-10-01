<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role?->value,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'profile' => ProfileResource::make($this->user->profile),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
