<?php

namespace App\Http\Requests\Admin;

class AdminAuditIndexRequest extends AdminListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'action' => ['nullable', 'string', 'max:80'],
            'target_type' => ['nullable', 'string', 'in:user,business,post,comment,reel'],
            'report_id' => ['nullable', 'integer', 'min:1'],
            'actor_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
