<?php

namespace Database\Factories;

use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryItem>
 */
class LibraryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_category_id' => LibraryCategory::factory(),
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(),
            'author' => fake()->optional()->name(),
            'file' => null,
            'link' => 'https://example.test/'.fake()->unique()->slug().'.pdf',
            'sort_order' => 0,
        ];
    }
}
