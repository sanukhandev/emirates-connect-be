<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'venue' => $this->venue,
            'emirate' => $this->emirate,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'attendees_count' => $this->whenCounted('attendees'),
            'is_rsvped' => $this->when(isset($this->is_rsvped), (bool) $this->is_rsvped),
        ];
    }
}
