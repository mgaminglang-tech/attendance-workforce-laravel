<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_employee_dashboard(): void
    {
        $this->get(route('employee.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_employee_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_employee_can_access_employee_dashboard(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Employee Dashboard')
            ->assertSee('navbar-toggler', false)
            ->assertSee($user->name)
            ->assertDontSee(route('admin.employees.index'), false);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee($user->name)
            ->assertDontSee(route('employee.dashboard'), false);
    }

    public function test_admin_cannot_access_employee_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertForbidden();
    }

    public function test_employee_dashboard_shows_the_authoritative_current_attendance_state(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 07:00:00', 'Asia/Manila'));
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();
        AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 11:30:00',
        ]);

        $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Currently working')
            ->assertSee('Sep 23, 2026 11:30:00 AM')
            ->assertSee('Time Out')
            ->assertDontSee('name="work_arrangement"', false);
    }

    public function test_admin_dashboard_uses_existing_workforce_and_attendance_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 18:00:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        $activeDepartment = Department::factory()->create(['is_active' => true]);
        Department::factory()->create(['is_active' => false]);
        $workingEmployee = Employee::factory()->for($activeDepartment)->create();
        $completedEmployee = Employee::factory()->for($activeDepartment)->create();
        AttendanceSession::factory()->for($workingEmployee)->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 23:00:00',
        ]);
        AttendanceSession::factory()->for($completedEmployee)->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'time_out_at' => '2026-09-24 17:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary === [
                'employees' => 2,
                'working' => 1,
                'completed_today' => 1,
                'active_departments' => 1,
            ])
            ->assertSee($workingEmployee->user->name)
            ->assertSee($completedEmployee->user->name);
    }

    public function test_hr_representative_dashboard_keeps_personal_and_department_workspaces_distinct(): void
    {
        $personalDepartment = Department::factory()->create(['name' => 'Human Resources']);
        $assignedDepartment = Department::factory()->create(['name' => 'Finance']);
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->for($personalDepartment)->create();
        DepartmentHrAssignment::factory()->for($assignedDepartment)->for($user)->create();

        $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('HR Representative')
            ->assertSee('Finance')
            ->assertSee(route('hr.team-attendance.index'), false)
            ->assertSee(route('hr.reports.attendance.index'), false)
            ->assertSee(route('employee.attendance.history'), false);
    }
}
