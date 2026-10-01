<?php

namespace App\Http\Requests\Business;

use App\Enums\Emirate;
use App\Enums\Industry;
use App\Http\Requests\Concerns\ValidatesWebUrls;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
{
    use ValidatesWebUrls;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['name', 'tagline', 'description', 'website_url', 'email', 'phone'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $data[$field] = $value === '' ? null : $value;
            }
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'industry' => ['sometimes', Rule::enum(Industry::class)],
            'emirate' => ['sometimes', Rule::enum(Emirate::class)],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:2048', $this->webUrlRule()],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
