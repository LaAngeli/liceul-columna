<?php

namespace Database\Factories;

use App\Enums\DocumentAccessLevel;
use App\Enums\DocumentCategory;
use App\Enums\DocumentSource;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => rtrim($this->faker->sentence(3), '.'),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(DocumentCategory::cases()),
            'access_level' => DocumentAccessLevel::Public,
            'visible_roles' => null,
            'source' => DocumentSource::Static,
            'file_path' => 'documents/'.$this->faker->uuid().'.pdf',
            'file_name' => $this->faker->slug(3).'.pdf',
            'file_size' => $this->faker->numberBetween(20_000, 2_000_000),
            'mime_type' => 'application/pdf',
            'version' => null,
            'is_published' => true,
            'uploaded_by_user_id' => null,
        ];
    }

    /** Document vizibil doar unui set de roluri. */
    public function forRoles(string ...$roles): static
    {
        return $this->state(fn (): array => [
            'access_level' => DocumentAccessLevel::RoleSpecific,
            'visible_roles' => array_values($roles),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
