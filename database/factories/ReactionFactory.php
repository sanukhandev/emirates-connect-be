<?php

namespace Database\Factories;

use App\Enums\ReactionType;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reaction> */
class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reactable_type' => 'post',
            'reactable_id' => 1,
            'type' => ReactionType::LIKE,
        ];
    }
}
