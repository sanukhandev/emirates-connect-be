<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class UploadBusinessLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['logo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120']];
    }
}
