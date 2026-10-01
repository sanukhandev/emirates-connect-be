<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UploadCoverImageRequest extends FormRequest
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
