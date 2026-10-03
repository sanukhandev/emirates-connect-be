<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;

class AdminUserIndexRequest extends AdminListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(UserStatus::cases(), 'value'))],
            'verified' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
