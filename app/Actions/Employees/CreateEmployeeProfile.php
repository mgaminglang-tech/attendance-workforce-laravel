<?php

namespace App\Actions\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

class CreateEmployeeProfile
{
    /**
     * @throws ValidationException
     */
    public function handle(
        User $user,
        string $employeeNumber,
        ?Department $department = null,
        ?string $jobTitle = null,
        EmploymentStatus $employmentStatus = EmploymentStatus::Active,
        ?DateTimeInterface $hiredAt = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): Employee {
        if (! $user->hasRole(UserRole::Employee)) {
            throw ValidationException::withMessages([
                'user_id' => 'An employee profile requires a user with the Employee role.',
            ]);
        }

        return $user->employee()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'employee_number' => $employeeNumber,
            'department_id' => $department?->getKey(),
            'job_title' => $jobTitle,
            'employment_status' => $employmentStatus,
            'hired_at' => $hiredAt,
        ]);
    }
}
