<?php

namespace Database\Factories;

use App\Enums\LibraryKind;
use App\Models\LibraryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LibraryCategory>
 */
class LibraryCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'title' => $title,
            'kind' => LibraryKind::Documents,
            'translations' => null,
            'sort_order' => 0,
            'published_at' => now(),
        ];
    }

    public function literature(): static
    {
        return $this->state(fn (): array => ['kind' => LibraryKind::Literature]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }
}
