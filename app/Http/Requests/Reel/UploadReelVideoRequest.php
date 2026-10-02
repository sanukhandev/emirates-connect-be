<?php

namespace App\Http\Requests\Reel;

use Illuminate\Foundation\Http\FormRequest;

class UploadReelVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video' => ['required', 'file', 'mimetypes:video/mp4', 'mimes:mp4', 'max:102400'],
        ];
    }
}
