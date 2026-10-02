<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerificationAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['action' => $this->action, 'from_status' => $this->from_status, 'to_status' => $this->to_status, 'metadata' => $this->metadata, 'created_at' => $this->created_at?->toISOString()];
    }
}
