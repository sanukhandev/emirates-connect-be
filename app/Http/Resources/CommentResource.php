<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => PostAuthorResource::make($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'replies_count' => $this->when(isset($this->replies_count), (int) $this->replies_count),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
        ];
    }
}
