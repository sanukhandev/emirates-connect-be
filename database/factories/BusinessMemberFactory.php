<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessMember> */
class BusinessMemberFactory extends Factory
{
    protected $model = BusinessMember::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'role' => BusinessRole::EDITOR,
        ];
    }
}
