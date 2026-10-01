<?php

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body')]);
    }

    public function rules(): array
    {
        return [
            'author_type' => ['required', Rule::in(['user', 'business'])],
            'business_id' => ['required_if:author_type,business', 'prohibited_if:author_type,user', 'nullable', 'integer', 'exists:businesses,id'],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
