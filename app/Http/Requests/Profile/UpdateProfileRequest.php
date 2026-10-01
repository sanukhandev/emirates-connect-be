<?php

namespace App\Http\Requests\Profile;

use App\Enums\Emirate;
use App\Enums\Industry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['display_name', 'headline', 'bio', 'job_title', 'company_name', 'industry', 'emirate', 'website_url', 'linkedin_url'] as $field) {
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
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'industry' => ['sometimes', 'nullable', Rule::enum(Industry::class)],
            'emirate' => ['sometimes', 'nullable', Rule::enum(Emirate::class)],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:2048', $this->urlRule(false)],
            'linkedin_url' => ['sometimes', 'nullable', 'string', 'max:2048', $this->urlRule(true)],
        ];
    }

    private function urlRule(bool $linkedin): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($linkedin): void {
            $parts = parse_url((string) $value);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
            $allowedSchemes = $linkedin ? ['https'] : ['http', 'https'];

            if (! in_array($scheme, $allowedSchemes, true) || $host === '') {
                $fail("The {$attribute} must be a valid web URL.");
            }

            if ($linkedin && ! in_array($host, ['linkedin.com', 'www.linkedin.com'], true)) {
                $fail('The linkedin URL must belong to linkedin.com.');
            }
        };
    }
}
