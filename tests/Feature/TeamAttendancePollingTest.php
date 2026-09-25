<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TeamAttendancePollingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_polling_endpoints_require_authentication(): void
    {
        $department = Department::factory()->create();

        $this->getJson(route('team-attendance.status'))->assertUnauthorized();
        $this->getJson(route('hr.team-attendance.status'))->assertUnauthorized();
        $this->getJson(route('admin.departments.team-attendance.status', $department))->assertUnauthorized();
    }

    public function test_employee_polling_is_server_scoped_and_contains_only_operational_fields(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Manila'));
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        [$viewer] = $this->employeeIn($finance, 'Finance Viewer');
        [$representative, $hrEmployee] = $this->employeeIn($finance, 'Finance HR Representative');
        [, $financeMember] = $this->employeeIn($finance, 'Finance Member');
        [, $itMember] = $this->employeeIn($it, 'IT Private Member');
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();
        $hrAttendanceSession = AttendanceSession::factory()->for($hrEmployee)->open()->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 07:30:00',
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);
        AttendanceSession::factory()->for($financeMember)->open()->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);
        AttendanceSession::factory()->for($itMember)->open()->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
        ]);

        $response = $this->actingAs($viewer)->getJson(route('team-attendance.status', [
            'department_id' => $it->id,
        ]))->assertOk()->assertJsonPath('department.name', 'Finance');
        $payload = $response->json();

        $this->assertSame(['department', 'summary', 'activity', 'last_updated'], array_keys($payload));
        $this->assertSame(
            ['employee_name', 'employee_initials', 'is_hr_representative', 'work_arrangement', 'event', 'event_time', 'occurred_at', 'date_label', 'net_hours'],
            array_keys($payload['activity'][0]),
        );
        $this->assertContains('Finance Member', array_column($payload['activity'], 'employee_name'));
        $this->assertContains('Office-Based', array_column($payload['activity'], 'work_arrangement'));
        $this->assertContains('Not Clocked In', array_column($payload['activity'], 'event'));
        $this->assertNotContains('Finance HR Representative', array_column($payload['activity'], 'employee_name'));
        $this->assertNotContains('IT Private Member', array_column($payload['activity'], 'employee_name'));
        $this->assertSame(['total' => 3, 'working' => 2, 'completed' => 0, 'on_leave' => 0, 'not_clocked_in' => 1], $payload['summary']);
        $this->assertArrayNotHasKey('email', $payload['activity'][0]);
        $this->assertArrayNotHasKey('correction_reason', $payload['activity'][0]);
        $this->assertModelExists($hrAttendanceSession);
    }

    public function test_team_attendance_refreshes_the_activity_feed_on_the_existing_interval(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($javascript);
        $this->assertStringContainsString('renderActivity(data.activity)', $javascript);
        $this->assertStringContainsString('arrangement.textContent = event.work_arrangement', $javascript);
        $this->assertStringContainsString("event.event === 'On Leave'", $javascript);
        $this->assertStringNotContainsString('event.net_hours', $javascript);
        $this->assertStringContainsString('if (document.hidden || refreshInFlight)', $javascript);
        $this->assertStringContainsString('refreshInFlight = false', $javascript);
        $this->assertStringContainsString('window.setInterval(refreshTeamAttendance, 20000)', $javascript);
        $this->assertStringNotContainsString('window.location.reload', $javascript);
    }

    public function test_initial_team_page_and_polling_payload_agree_on_leave_status(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Manila'));
        $department = Department::factory()->create();
        [$viewer] = $this->employeeIn($department, 'Viewer');
        [, $onLeave] = $this->employeeIn($department, 'On Leave Employee');
        EmployeeLeaveDay::factory()->for($onLeave)->create(['leave_date' => '2026-09-24']);

        $this->actingAs($viewer)->get(route('team-attendance.index'))
            ->assertOk()
            ->assertSee('On Leave Employee')
            ->assertSee('On Leave')
            ->assertSee('data-summary="on_leave">1', false);

        $this->actingAs($viewer)->getJson(route('team-attendance.status'))
            ->assertOk()
            ->assertJsonPath('summary.on_leave', 1)
            ->assertJsonFragment([
                'employee_name' => 'On Leave Employee',
                'event' => 'On Leave',
                'event_time' => null,
                'work_arrangement' => null,
            ]);
    }

    public function test_hr_polling_uses_assignment_scope_instead_of_employee_department(): void
    {
        $ownDepartment = Department::factory()->create(['name' => 'Human Resources']);
        $finance = Department::factory()->create(['name' => 'Finance']);
        [$representative] = $this->employeeIn($ownDepartment, 'HR Representative');
        $this->employeeIn($finance, 'Finance Member');
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();

        $this->actingAs($representative)->getJson(route('hr.team-attendance.status'))
            ->assertOk()
            ->assertJsonPath('department.name', 'Finance')
            ->assertJsonFragment(['employee_name' => 'Finance Member'])
            ->assertJsonMissing(['employee_name' => 'HR Representative']);
    }

    public function test_global_admin_can_poll_any_department_and_employee_cannot_poll_admin_department_url(): void
    {
        $admin = User::factory()->admin()->create();
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        [$financeEmployee] = $this->employeeIn($finance, 'Finance Employee');

        $this->actingAs($admin)
            ->getJson(route('admin.departments.team-attendance.status', $finance))
            ->assertOk()->assertJsonPath('department.name', 'Finance');
        $this->actingAs($admin)
            ->getJson(route('admin.departments.team-attendance.status', $it))
            ->assertOk()->assertJsonPath('department.name', 'IT');

        $this->actingAs($financeEmployee)
            ->getJson(route('admin.departments.team-attendance.status', $it))
            ->assertForbidden();
    }

    /** @return array{User, Employee} */
    private function employeeIn(Department $department, string $name): array
    {
        $user = User::factory()->employee()->create(['name' => $name]);
        $employee = Employee::factory()->for($user)->for($department)->create();

        return [$user, $employee];
    }
}
