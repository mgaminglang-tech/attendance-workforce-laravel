<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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
            ->assertSee('Employee profile not found')
            ->assertDontSee('Record Leave')
            ->assertDontSee('id="record-leave-modal"', false)
            ->assertDontSee(route('admin.employees.index'), false);
    }

    public function test_employee_can_record_leave_from_a_fresh_dashboard_visit(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00', 'Asia/Manila'));
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Record Leave')
            ->assertSee('data-bs-target="#record-leave-modal"', false)
            ->assertSee('Record time away')
            ->assertSee('href="'.route('employee.attendance.history').'">View all', false);

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $forms = $xpath->query('//*[@id="record-leave-modal"]//form');
        $this->assertCount(1, $forms);
        $form = $forms->item(0);
        $this->assertSame('POST', $form->getAttribute('method'));
        $this->assertSame(route('employee.attendance.leave.store'), $form->getAttribute('action'));
        $this->assertCount(1, $xpath->query('.//input[@name="_token"]', $form));
        $this->assertCount(2, $xpath->query('.//input[@type="date" and @value="2026-10-06"]', $form));
        $this->assertCount(0, $xpath->query('.//input[@name="employee_id"]', $form));

        $this->from(route('employee.dashboard'))->post($form->getAttribute('action'), [
            'from_date' => '2026-10-07',
            'to_date' => '2026-10-08',
        ])->assertRedirect(route('employee.attendance.index'))
            ->assertSessionHas('status', 'Leave recorded for 2 days.');

        $this->assertSame(
            ['2026-10-07', '2026-10-08'],
            $employee->leaveDays()->orderBy('leave_date')->pluck('leave_date')->map->toDateString()->all(),
        );
    }

    public function test_employee_dashboard_shows_only_own_current_and_upcoming_leave(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00', 'Asia/Manila'));
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();
        $todayLeave = EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-10-06']);
        $upcomingLeave = EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-10-08']);
        $pastLeave = EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-10-05']);
        $otherLeave = EmployeeLeaveDay::factory()->create(['leave_date' => '2026-10-09']);

        $this->actingAs($user)->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Record Leave')
            ->assertSee('Current and upcoming leave')
            ->assertSee('On Leave')
            ->assertSee('Thu, Oct 8')
            ->assertSee(route('employee.attendance.leave.destroy', $todayLeave), false)
            ->assertSee(route('employee.attendance.leave.destroy', $upcomingLeave), false)
            ->assertDontSee(route('employee.attendance.leave.destroy', $pastLeave), false)
            ->assertDontSee(route('employee.attendance.leave.destroy', $otherLeave), false)
            ->assertDontSee(route('employee.attendance.time-in'), false);
    }

    public function test_leave_validation_returns_to_dashboard_with_the_shared_modal_ready_to_reopen(): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->create();

        $this->actingAs($user)->from(route('employee.dashboard'))
            ->post(route('employee.attendance.leave.store'), [
                'from_date' => '2026-10-08',
                'to_date' => '2026-10-07',
            ])->assertRedirect(route('employee.dashboard'))
            ->assertSessionHasErrors(['to_date' => 'The last day must be on or after the first day.']);

        $this->withCookie(config('session.cookie'), session()->getId())->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('data-open-modal-on-load="true"', false)
            ->assertSee('value="2026-10-08"', false)
            ->assertSee('value="2026-10-07"', false)
            ->assertSee('The last day must be on or after the first day.');

        $this->assertDatabaseCount('employee_leave_days', 0);
    }

    #[DataProvider('nonActiveAccountStatuses')]
    public function test_non_active_employee_account_cannot_access_dashboard(AccountStatus $status): void
    {
        $user = User::factory()->employee()->create(['account_status' => $status]);
        Employee::factory()->for($user)->create();

        $this->actingAs($user)->get(route('employee.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your account is not active. Please contact an administrator.']);

        $this->assertGuest();
    }

    /** @return array<string, array{AccountStatus}> */
    public static function nonActiveAccountStatuses(): array
    {
        return [
            'pending' => [AccountStatus::Pending],
            'disabled' => [AccountStatus::Disabled],
        ];
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee($user->name)
            ->assertDontSee('Add employee')
            ->assertDontSee(route('admin.employees.create'), false)
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
            ->assertSee('September 23, 2026')
            ->assertSee('11:30 AM')
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
                'not_clocked_in' => 0,
            ])
            ->assertSee($workingEmployee->user->name)
            ->assertSee($completedEmployee->user->name)
            ->assertSee('Department overview')
            ->assertDontSee('Quick actions');
    }

    public function test_hr_representative_dashboard_keeps_personal_and_department_workspaces_distinct(): void
    {
        $personalDepartment = Department::factory()->create(['name' => 'Human Resources']);
        $assignedDepartment = Department::factory()->create(['name' => 'Finance']);
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->for($personalDepartment)->create();
        DepartmentHrAssignment::factory()->for($assignedDepartment)->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('HR Representative')
            ->assertSee('Finance')
            ->assertSee('HR reporting responsibility')
            ->assertDontSee('hr-assignment-actions', false)
            ->assertSee(route('employee.attendance.history'), false)
            ->assertDontSee('Personal tools');

        $this->assertSame(2, substr_count($response->getContent(), route('hr.team-attendance.index')));
        $this->assertSame(2, substr_count($response->getContent(), route('hr.reports.attendance.index')));
    }
}
