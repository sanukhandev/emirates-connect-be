<?php

namespace App\Http\Resources;

use App\Enums\ReelStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $published = $this->status === ReelStatus::PUBLISHED;
        $managementView = ! $published && ($request->user()?->can('view', $this->resource) ?? false);

        return [
            'id' => $this->id,
            'caption' => $this->caption,
            'status' => $this->status?->value,
            'author' => ReelAuthorResource::make($this->author),
            'playback_url' => $published && $this->playback_disk && $this->playback_path
                ? Storage::disk($this->playback_disk)->url($this->playback_path)
                : null,
            'thumbnail_url' => $published && $this->thumbnail_disk && $this->thumbnail_path
                ? Storage::disk($this->thumbnail_disk)->url($this->thumbnail_path)
                : null,
            'duration_seconds' => $this->duration_seconds,
            'width' => $this->width,
            'height' => $this->height,
            'processing_error' => $managementView && $this->status === ReelStatus::FAILED ? 'Video processing failed.' : null,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
