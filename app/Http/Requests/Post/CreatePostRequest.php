<?php

namespace App\Http\Requests\Post;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    public function rules(): array
    {
        return [
            'author_type' => ['required', Rule::in(['user', 'business'])],
            'business_id' => ['required_if:author_type,business', 'prohibited_if:author_type,user', 'nullable', 'integer', 'exists:businesses,id'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'media' => ['sometimes', 'array', 'max:4'],
            'media.*' => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (blank($this->input('body')) && ! $this->hasFile('media')) {
                $validator->errors()->add('body', 'A post needs text or at least one image.');
            }
        });
    }
}
