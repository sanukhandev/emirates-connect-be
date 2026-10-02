<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('unread')) {
            $this->merge(['unread' => filter_var($this->input('unread'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }
    }

    public function rules(): array
    {
        return ['unread' => ['sometimes', 'boolean'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50']];
    }
}
