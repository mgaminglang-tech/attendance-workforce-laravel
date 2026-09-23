<?php

namespace Database\Factories;

use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeInvitation>
 */
class EmployeeInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'user_id' => User::factory()->employee()->pending(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(48),
            'accepted_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now(),
        ]);
    }
}
