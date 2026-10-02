<?php

namespace App\Http\Requests\Verification;

use Illuminate\Foundation\Http\FormRequest;

class SubmitBusinessVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_business_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:120'],
            'licence_number' => ['nullable', 'string', 'max:120'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'document_types' => ['required', 'array', 'min:1', 'max:5'],
            'document_types.*' => ['required', 'string', 'in:trade_licence,certificate_of_incorporation,proof_of_business,other_supporting_document'],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:10240'],
        ];
    }
}
