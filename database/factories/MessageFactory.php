<?php

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_user_id' => User::factory(),
            'recipient_user_id' => User::factory(),
            'student_id' => null,
            'parent_id' => null,
            'type' => MessageType::Direct,
            'subject' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function audience(): static
    {
        return $this->state(fn (): array => ['type' => MessageType::Audience]);
    }
}
