<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'actor' => $this->actorResource(),
            'subject' => $this->subjectResource(),
            'data' => $this->data ?? [],
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function actorResource(): ?array
    {
        return match (true) {
            $this->actor instanceof User && $this->actor->account_status?->value === 'active' => [
                'type' => 'user', 'id' => $this->actor->id, 'display_name' => $this->actor->profile?->display_name ?: $this->actor->name,
                'avatar_url' => $this->actor->profile?->avatar_path ? Storage::disk('public')->url($this->actor->profile->avatar_path) : null,
                'headline' => $this->actor->profile?->headline, 'is_verified' => $this->actor->profile?->verification_status?->value === 'approved',
            ],
            $this->actor instanceof Business && $this->actor->status?->value === 'active' => [
                'type' => 'business', 'id' => $this->actor->id, 'name' => $this->actor->name, 'slug' => $this->actor->slug,
                'logo_url' => $this->actor->logo_path ? Storage::disk('public')->url($this->actor->logo_path) : null, 'is_verified' => $this->actor->verification_status?->value === 'approved',
            ],
            default => null,
        };
    }

    private function subjectResource(): ?array
    {
        return match (true) {
            $this->subject instanceof Post => ['type' => 'post', 'id' => $this->subject->id],
            $this->subject instanceof Comment => ['type' => 'comment', 'id' => $this->subject->id],
            $this->subject instanceof User => ['type' => 'user', 'id' => $this->subject->id],
            $this->subject instanceof Business => ['type' => 'business', 'id' => $this->subject->id],
            default => null,
        };
    }
}
