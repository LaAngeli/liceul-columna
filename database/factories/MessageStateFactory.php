<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageState>
 */
class MessageStateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'user_id' => User::factory(),
            'starred_at' => null,
            'trashed_at' => null,
        ];
    }

    public function starred(): static
    {
        return $this->state(fn (): array => ['starred_at' => now()]);
    }

    public function trashed(): static
    {
        return $this->state(fn (): array => ['trashed_at' => now()]);
    }
}
