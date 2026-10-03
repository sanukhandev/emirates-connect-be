<?php

namespace App\Http\Requests\Admin;

use App\Enums\BusinessStatus;

class AdminBusinessIndexRequest extends AdminListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(BusinessStatus::cases(), 'value'))],
            'verified' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
