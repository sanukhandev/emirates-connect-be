<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerificationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subject = $this->whenLoaded('subject');

        return [
            'id' => $this->id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject' => $subject instanceof User
                ? ['type' => 'user', 'id' => $subject->id, 'name' => $subject->profile?->display_name ?? $subject->name, 'is_verified' => $subject->profile?->verification_status?->value === 'approved']
                : ($subject instanceof Business ? ['type' => 'business', 'id' => $subject->id, 'name' => $subject->name, 'slug' => $subject->slug, 'is_verified' => $subject->verification_status?->value === 'approved'] : null),
            'status' => $this->status?->value,
            'data' => $this->data,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'documents' => VerificationDocumentResource::collection($this->whenLoaded('documents')),
            'audit' => VerificationAuditResource::collection($this->whenLoaded('auditLogs')),
        ];
    }
}
