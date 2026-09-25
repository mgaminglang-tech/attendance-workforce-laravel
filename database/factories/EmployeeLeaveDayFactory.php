<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeLeaveDay>
 */
class EmployeeLeaveDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_date' => fake()->unique()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
        ];
    }
}
