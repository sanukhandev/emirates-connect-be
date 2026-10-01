<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Business;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $user = User::factory();

        return [
            'author_type' => 'user',
            'author_id' => $user,
            'body' => fake()->sentence(),
            'status' => PostStatus::PUBLISHED,
            'published_at' => now(),
            'created_by' => $user,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PostStatus::DRAFT, 'published_at' => null]);
    }

    public function forBusiness(Business $business, User $actor): static
    {
        return $this->state([
            'author_type' => 'business',
            'author_id' => $business->id,
            'created_by' => $actor->id,
        ]);
    }
}
