<?php

namespace App\Http\Requests\Business;

use App\Enums\BusinessRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(BusinessRole::class), Rule::notIn([BusinessRole::OWNER->value])]];
    }
}
