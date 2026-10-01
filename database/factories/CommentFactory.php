<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comment> */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        $user = User::factory();

        return [
            'post_id' => Post::factory(),
            'author_type' => 'user',
            'author_id' => $user,
            'body' => fake()->sentence(),
            'created_by' => $user,
        ];
    }

    public function reply(Comment $parent): static
    {
        return $this->state(['post_id' => $parent->post_id, 'parent_id' => $parent->id]);
    }
}
