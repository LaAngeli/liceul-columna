<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'file_path' => 'documents/'.$this->faker->uuid().'.pdf',
            'file_name' => $this->faker->slug(3).'.pdf',
            'file_size' => $this->faker->numberBetween(20_000, 2_000_000),
            'mime_type' => 'application/pdf',
            'version_label' => null,
            'uploaded_by_user_id' => null,
        ];
    }
}
