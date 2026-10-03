<?php

namespace App\Http\Requests\Admin;

use App\Enums\VerificationStatus;

class AdminVerificationIndexRequest extends AdminListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(VerificationStatus::cases(), 'value'))],
            'subject_type' => ['nullable', 'string', 'in:user,business'],
        ];
    }
}
