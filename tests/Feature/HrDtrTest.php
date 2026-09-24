<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HrDtrTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_hr_representative_can_preview_and_download_assigned_employee_dtr(): void
    {
        $finance = Department::factory()->create(['name' => 'Finance']);
        $representative = User::factory()->employee()->has(Employee::factory())->create();
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();
        $employee = Employee::factory()
            ->for(User::factory()->employee()->disabled()->state(['name' => 'Historical Employee']))
            ->for($finance)
            ->inactive()
            ->create(['employee_number' => 'FIN-HIST']);
        AttendanceSession::factory()->for($employee)->create(['work_date' => '2026-09-12']);

        $this->actingAs($representative)->get(route('hr.dtr.preview', [
            'employee' => $employee,
            'month' => '2026-09',
        ]))->assertOk()
            ->assertSee('September 2026')
            ->assertSee('type="month"', false)
            ->assertSee('value="2026-09"', false)
            ->assertSee('Historical Employee')
            ->assertViewHas('dtr', fn (array $dtr): bool => $dtr['employee']->is($employee));

        $response = $this->actingAs($representative)->get(route('hr.dtr.pdf', [
            'employee' => $employee,
            'month' => '2026-09',
        ]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_hr_representative_cannot_access_another_department_employee_dtr(): void
    {
        $finance = Department::factory()->create();
        $it = Department::factory()->create();
        $representative = User::factory()->employee()->has(Employee::factory())->create();
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();
        $itEmployee = Employee::factory()
            ->for(User::factory()->employee()->disabled())
            ->for($it)
            ->inactive()
            ->create();
        $query = ['employee' => $itEmployee, 'month' => '2026-09'];

        $this->actingAs($representative)->get(route('hr.dtr.preview', $query))->assertForbidden();
        $this->actingAs($representative)->get(route('hr.dtr.pdf', $query))->assertForbidden();
    }

    public function test_unassigned_employee_cannot_use_hr_dtr_routes_and_retains_own_dtr(): void
    {
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();

        $this->actingAs($user)->get(route('hr.dtr.preview', [
            'employee' => $employee,
            'month' => '2026-09',
        ]))->assertForbidden();

        $this->actingAs($user)->get(route('employee.dtr.preview', [
            'month' => '2026-09',
        ]))->assertOk();
    }

    public function test_inactive_hr_representative_cannot_access_assigned_employee_dtr(): void
    {
        $department = Department::factory()->create();
        $representative = User::factory()->employee()->create();
        Employee::factory()->for($representative)->for($department)->inactive()->create();
        DepartmentHrAssignment::factory()->for($department)->for($representative)->create();
        $employee = Employee::factory()->for($department)->create();
        $query = ['employee' => $employee, 'month' => '2026-09'];

        $this->actingAs($representative)->get(route('hr.dtr.preview', $query))->assertForbidden();
        $this->actingAs($representative)->get(route('hr.dtr.pdf', $query))->assertForbidden();
    }
}
