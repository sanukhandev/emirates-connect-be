<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PostMedia> */
class PostMediaFactory extends Factory
{
    protected $model = PostMedia::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'type' => 'image',
            'path' => 'posts/fake/image.png',
            'mime_type' => 'image/png',
            'size' => 100,
            'width' => 1,
            'height' => 1,
            'sort_order' => 0,
        ];
    }
}
