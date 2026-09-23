<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminDepartmentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_access_and_create_departments(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.departments.index'))
            ->assertOk()
            ->assertSee('Departments');

        $this->actingAs($admin)
            ->post(route('admin.departments.store'), [
                'name' => 'Operations',
                'code' => ' ops ',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.departments.index'));

        $this->assertDatabaseHas('departments', [
            'name' => 'Operations',
            'code' => 'OPS',
            'is_active' => true,
        ]);
    }

    public function test_employee_cannot_manage_departments(): void
    {
        $employeeUser = User::factory()->employee()->create();

        $this->actingAs($employeeUser)
            ->get(route('admin.departments.index'))
            ->assertForbidden();
    }

    public function test_department_can_be_deactivated_without_losing_employee_relationship(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $employee = Employee::factory()->for($department)->create();

        $this->actingAs($admin)
            ->put(route('admin.departments.update', $department), [
                'name' => $department->name,
                'code' => $department->code,
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.departments.index'));

        $this->assertFalse($department->refresh()->is_active);
        $this->assertTrue($employee->refresh()->department->is($department));
    }
}
