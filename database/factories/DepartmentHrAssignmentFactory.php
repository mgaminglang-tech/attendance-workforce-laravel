<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentHrAssignment>
 */
class DepartmentHrAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'user_id' => User::factory()->employee()->has(Employee::factory()),
            'assigned_by_user_id' => User::factory()->admin(),
            'assigned_at' => now(config('app.timezone')),
        ];
    }
}
