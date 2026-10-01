<?php

namespace App\Http\Resources;

use App\Support\ReactionSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'status' => $this->status?->value,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'author' => PostAuthorResource::make($this->author),
            'media' => PostMediaResource::collection($this->whenLoaded('media')),
            'reactions' => ReactionSummary::toArray($this->resource),
        ];
    }
}
