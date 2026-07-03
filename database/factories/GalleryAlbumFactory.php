<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryAlbum>
 */
class GalleryAlbumFactory extends Factory
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
            'translations' => null,
            'sort_order' => 0,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    /**
     * Atașează imagini (căi) în ordinea dată, ca înregistrări GalleryImage.
     *
     * @param  list<string>  $paths
     */
    public function withImages(array $paths): static
    {
        return $this->afterCreating(function (GalleryAlbum $album) use ($paths): void {
            $order = 0;
            foreach ($paths as $path) {
                $order++;
                $album->images()->create(['path' => $path, 'sort_order' => $order]);
            }
        });
    }
}
