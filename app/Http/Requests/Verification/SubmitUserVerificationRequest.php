<?php

namespace App\Http\Requests\Verification;

use Illuminate\Foundation\Http\FormRequest;

class SubmitUserVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'document_types' => ['required', 'array', 'min:1', 'max:5'],
            'document_types.*' => ['required', 'string', 'in:identity_document,proof_of_professional_identity,other_supporting_document'],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:10240'],
        ];
    }
}
