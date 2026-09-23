<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_employee_belongs_to_a_user_and_user_has_one_employee_profile(): void
    {
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();

        $this->assertTrue($employee->user->is($user));
        $this->assertTrue($user->employee->is($employee));
    }

    public function test_employee_and_department_relationships_load_correctly(): void
    {
        $department = Department::factory()->create();
        $employee = Employee::factory()->for($department)->create();

        $this->assertTrue($employee->department->is($department));
        $this->assertTrue($department->employees->contains($employee));
    }

    public function test_employee_profile_can_exist_without_a_department(): void
    {
        $employee = Employee::factory()->create(['department_id' => null]);

        $this->assertModelExists($employee);
        $this->assertNull($employee->department);
    }

    public function test_employee_number_must_be_unique(): void
    {
        Employee::factory()->create(['employee_number' => 'EMP-000001']);

        $this->expectException(QueryException::class);

        Employee::factory()->create(['employee_number' => 'EMP-000001']);
    }

    public function test_user_cannot_have_a_second_employee_profile(): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        Employee::factory()->for($user)->create();
    }

    public function test_employment_status_and_hired_date_are_cast_to_domain_types(): void
    {
        $employee = Employee::factory()->inactive()->create(['hired_at' => '2026-01-15']);

        $this->assertSame(EmploymentStatus::Inactive, $employee->employment_status);
        $this->assertSame('2026-01-15', $employee->hired_at->toDateString());
    }

    public function test_department_with_employees_cannot_be_deleted(): void
    {
        $department = Department::factory()->create();
        Employee::factory()->for($department)->create();

        $this->expectException(QueryException::class);

        $department->delete();
    }

    public function test_user_with_employee_profile_cannot_be_deleted(): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        $user->delete();
    }
}
