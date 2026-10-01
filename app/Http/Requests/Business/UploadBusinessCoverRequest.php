<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class UploadBusinessCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cover_image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192']];
    }
}
