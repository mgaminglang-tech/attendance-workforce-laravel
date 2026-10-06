<?php

namespace Tests\Feature;

use App\Actions\Employees\CreateEmployeeProfile;
use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateEmployeeProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_employee_role_user_can_receive_an_employee_profile(): void
    {
        $user = User::factory()->employee()->create();
        $department = Department::factory()->create();

        $employee = (new CreateEmployeeProfile)->handle(
            user: $user,
            employeeNumber: 'EMP-100001',
            department: $department,
            jobTitle: 'Software Engineer',
            employmentStatus: EmploymentStatus::Active,
            hiredAt: new \DateTimeImmutable('2026-02-01'),
        );

        $this->assertModelExists($employee);
        $this->assertTrue($employee->user->is($user));
        $this->assertTrue($employee->department->is($department));
        $this->assertSame('EMP-100001', $employee->employee_number);
        $this->assertSame(EmploymentStatus::Active, $employee->employment_status);
        $this->assertSame('2026-02-01', $employee->hired_at->toDateString());
        $this->assertNull($employee->first_name);
        $this->assertNull($employee->last_name);
        $this->assertSame($user->name, $employee->user->name);
    }

    public function test_admin_role_user_cannot_receive_an_employee_profile(): void
    {
        $user = User::factory()->admin()->create();

        try {
            (new CreateEmployeeProfile)->handle(
                user: $user,
                employeeNumber: 'EMP-100002',
            );
            $this->fail('Expected employee profile creation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'An employee profile requires a user with the Employee role.',
                $exception->errors()['user_id'][0],
            );
        }

        $this->assertSame(0, Employee::count());
    }
}
