<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['dismiss', 'content_removed', 'account_suspended', 'business_suspended'])],
            'resolution' => ['required', 'string', 'max:2000'],
        ];
    }
}
