<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'target_type' => $this->target_type, 'target_id' => $this->target_id,
            'reason' => $this->reason?->value, 'details' => $this->details, 'status' => $this->status?->value,
            'moderation_action' => $this->moderation_action?->value,
            'reporter' => $this->person($this->reporter), 'reviewer' => $this->person($this->reviewer),
            'target' => $this->targetSummary(), 'reviewed_at' => $this->reviewed_at?->toISOString(),
            'resolution' => $this->resolution, 'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'audit' => $this->whenLoaded('auditLogs', fn () => $this->auditLogs->map(fn ($log) => [
                'id' => $log->id, 'action' => $log->action, 'target_type' => $log->target_type,
                'target_id' => $log->target_id, 'created_at' => $log->created_at?->toISOString(),
            ])->values()),
        ];
    }

    private function person(?User $user): ?array
    {
        return $user ? ['type' => 'user', 'id' => $user->id, 'display_name' => $user->profile?->display_name ?: $user->name] : null;
    }

    private function targetSummary(): ?array
    {
        $target = $this->target;

        return match (true) {
            $target instanceof User => ['type' => 'user', 'id' => $target->id, 'display_name' => $target->profile?->display_name ?: $target->name, 'account_status' => $target->account_status?->value],
            $target instanceof Business => ['type' => 'business', 'id' => $target->id, 'name' => $target->name, 'slug' => $target->slug, 'status' => $target->status?->value],
            $target instanceof Post => ['type' => 'post', 'id' => $target->id, 'body' => str($target->body)->limit(280)->toString(), 'created_at' => $target->created_at?->toISOString()],
            $target instanceof Comment => ['type' => 'comment', 'id' => $target->id, 'body' => str($target->body)->limit(280)->toString(), 'post_id' => $target->post_id],
            $target instanceof Reel => ['type' => 'reel', 'id' => $target->id, 'caption' => $target->caption, 'status' => $target->status?->value, 'published_at' => $target->published_at?->toISOString()],
            default => null,
        };
    }
}
