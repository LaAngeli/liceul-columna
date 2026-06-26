<?php

namespace Database\Factories;

use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    protected $model = DocumentRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => DocumentRequestType::Adeverinta,
            'student_id' => Student::factory(),
            'requested_by_user_id' => User::factory(),
            'payload' => ['details' => $this->faker->sentence()],
            'pdf_path' => null,
            'status' => RequestStatus::Pending,
        ];
    }

    public function ofType(DocumentRequestType $type): static
    {
        return $this->state(fn (): array => ['type' => $type->value]);
    }
}
