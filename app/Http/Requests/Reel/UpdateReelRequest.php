<?php

namespace App\Http\Requests\Reel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReelRequest extends FormRequest
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
            'caption' => ['sometimes', 'nullable', 'string', 'max:2200'],
            'author_type' => ['prohibited'],
            'author_id' => ['prohibited'],
            'business_id' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'source_path' => ['prohibited'],
            'playback_path' => ['prohibited'],
        ];
    }
}
