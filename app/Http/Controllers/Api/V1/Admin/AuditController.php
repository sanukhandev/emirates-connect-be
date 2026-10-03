<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAuditIndexRequest;
use App\Http\Resources\AdminModerationAuditResource;
use App\Models\ModerationAuditLog;

class AuditController extends Controller
{
    public function moderation(AdminAuditIndexRequest $request)
    {
        $query = ModerationAuditLog::query()->with('actor.profile')->latest('created_at')->latest('id');
        foreach (['action', 'target_type', 'report_id', 'actor_user_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return AdminModerationAuditResource::collection($query->paginate($request->integer('per_page', 20)));
    }
}
