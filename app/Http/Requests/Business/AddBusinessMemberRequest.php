<?php

namespace App\Http\Requests\Business;

use App\Enums\BusinessRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddBusinessMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::enum(BusinessRole::class), Rule::notIn([BusinessRole::OWNER->value])],
        ];
    }
}
