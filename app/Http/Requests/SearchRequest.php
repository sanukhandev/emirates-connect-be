<?php

namespace App\Http\Requests;

use App\Enums\Emirate;
use App\Enums\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $this->merge(['q' => trim((string) $this->input('q')) ?: null]);
        }

        if ($this->has('verified')) {
            $this->merge(['verified' => filter_var($this->input('verified'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200', 'min:2'],
            'type' => ['nullable', Rule::in(['all', 'users', 'businesses'])],
            'industry' => ['nullable', Rule::enum(Industry::class)],
            'emirate' => ['nullable', Rule::enum(Emirate::class)],
            'verified' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
