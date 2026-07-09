<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageAttachment>
 */
class MessageAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'disk' => 'local',
            'path' => 'message-attachments/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
