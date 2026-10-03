<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(['user', 'business', 'post', 'comment', 'reel'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->input('reason') === ReportReason::OTHER->value && blank($this->input('details'))) {
            throw ValidationException::withMessages(['details' => 'Details are required for this reason.']);
        }
    }
}
