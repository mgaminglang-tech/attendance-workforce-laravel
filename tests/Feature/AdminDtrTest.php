<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminDtrTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_global_admin_can_preview_an_employee_from_any_department(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Remote Operations']);
        $employee = Employee::factory()
            ->for(User::factory()->employee()->state(['name' => 'Department Employee']))
            ->for($department)
            ->create();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-12',
            'time_in_at' => '2026-09-12 07:45:00',
            'time_out_at' => '2026-09-12 16:30:00',
            'work_arrangement' => WorkArrangement::FieldBased,
        ]);
        EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-13']);

        $this->actingAs($admin)->get(route('admin.dtr.index'))
            ->assertOk()
            ->assertSee($employee->employee_number)
            ->assertSee('Department Employee')
            ->assertSee('role="combobox"', false)
            ->assertSee('name="employee_id"', false)
            ->assertSee('data-employee-number="'.$employee->employee_number.'"', false)
            ->assertViewHas('employees', fn ($employees): bool => $employees->contains($employee));

        $this->actingAs($admin)->get(route('admin.dtr.preview', [
            'employee_id' => $employee->id,
            'month' => '2026-09',
        ]))->assertOk()
            ->assertSee('September 2026')
            ->assertSee('type="month"', false)
            ->assertSee('value="2026-09"', false)
            ->assertSee('Remote Operations')
            ->assertDontSee('7:45 AM')
            ->assertDontSee('4:30 PM')
            ->assertSee('7.75')
            ->assertSee('FIELD-BASED')
            ->assertSee('ON LEAVE')
            ->assertViewHas('dtr', fn (array $dtr): bool => $dtr['employee']->is($employee));
    }

    public function test_global_admin_can_download_any_employee_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create([
            'employee_number' => 'EMP-ADMIN-DTR',
            'first_name' => 'Cris David',
            'last_name' => 'Castro',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dtr.pdf', [
            'employee_id' => $employee->id,
            'month' => '2026-09',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'filename=DTR_CASTRO_CRIS-DAVID_2026-09.pdf',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_employee_and_hr_representative_cannot_access_admin_dtr_endpoints(): void
    {
        $department = Department::factory()->create();
        $representative = User::factory()->employee()->create();
        Employee::factory()->for($representative)->create();
        DepartmentHrAssignment::factory()->for($department)->for($representative)->create();
        $target = Employee::factory()->create();
        $query = ['employee_id' => $target->id, 'month' => '2026-09'];

        $this->actingAs($representative)->get(route('admin.dtr.index'))->assertForbidden();
        $this->actingAs($representative)->get(route('admin.dtr.preview', $query))->assertForbidden();
        $this->actingAs($representative)->get(route('admin.dtr.pdf', $query))->assertForbidden();
    }

    public function test_admin_dtr_selection_validates_employee_and_month(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.dtr.index'))
            ->get(route('admin.dtr.preview', [
                'employee_id' => 999999,
                'month' => '2026-13',
            ]))->assertRedirect(route('admin.dtr.index'))
            ->assertSessionHasErrors([
                'employee_id' => 'The selected employee is unavailable.',
                'month' => 'The DTR month must use the YYYY-MM format.',
            ]);
    }

    public function test_guest_and_disabled_admin_cannot_access_admin_dtr(): void
    {
        $disabledAdmin = User::factory()->admin()->disabled()->create();

        $this->get(route('admin.dtr.index'))->assertRedirect(route('login'));
        $this->actingAs($disabledAdmin)->get(route('admin.dtr.index'))->assertRedirect(route('login'));
    }
}
