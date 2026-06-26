<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(6), '.');

        return [
            'wp_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 1000000),
            'category' => $this->faker->randomElement(['actualitati', 'blog']),
            'excerpt' => $this->faker->optional()->paragraph(),
            'content' => '<p>'.implode('</p><p>', (array) $this->faker->paragraphs(3)).'</p>',
            'image' => null,
            'published_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }

    public function actualitati(): static
    {
        return $this->state(fn (): array => ['category' => 'actualitati']);
    }

    public function blog(): static
    {
        return $this->state(fn (): array => ['category' => 'blog']);
    }
}
