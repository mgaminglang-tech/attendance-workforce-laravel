<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'employee_number' => fake()->unique()->numerify('EMP-######'),
            'department_id' => null,
            'job_title' => fake()->jobTitle(),
            'employment_status' => EmploymentStatus::Active,
            'hired_at' => fake()->optional()->dateTimeBetween('-10 years', 'now'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'employment_status' => EmploymentStatus::Inactive,
        ]);
    }
}
