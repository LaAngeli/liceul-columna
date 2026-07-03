<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gallery_album_id' => GalleryAlbum::factory(),
            'path' => '/images/galerie/general/'.fake()->unique()->slug().'.jpg',
            'sort_order' => 0,
        ];
    }
}
