<?php

namespace App\Http\Requests\Reel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('caption') && is_string($this->input('caption'))) {
            $this->merge(['caption' => trim($this->input('caption'))]);
        }
    }

    public function rules(): array
    {
        return [
            'author_type' => ['required', Rule::in(['user', 'business'])],
            'business_id' => ['required_if:author_type,business', 'prohibited_if:author_type,user', 'nullable', 'integer', 'exists:businesses,id'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'author_id' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
