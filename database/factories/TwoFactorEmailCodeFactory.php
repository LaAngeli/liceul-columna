<?php

namespace Database\Factories;

use App\Models\TwoFactorEmailCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TwoFactorEmailCode>
 */
class TwoFactorEmailCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code_hash' => hash('sha256', '123456'),
            'pending_email' => null,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
            'attempts' => 0,
        ];
    }
}
