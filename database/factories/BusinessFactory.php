<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Enums\Emirate;
use App\Enums\Industry;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Business> */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'tagline' => fake()->optional()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'industry' => fake()->randomElement(Industry::cases()),
            'emirate' => fake()->randomElement(Emirate::cases()),
            'website_url' => 'https://example.com',
            'email' => fake()->companyEmail(),
            'phone' => '+971500000000',
            'status' => BusinessStatus::ACTIVE,
            'created_by' => User::factory(),
        ];
    }
}
