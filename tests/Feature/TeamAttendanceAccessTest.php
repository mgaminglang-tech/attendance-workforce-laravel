<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TeamAttendanceAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_employee_can_view_only_their_own_department_workspace_and_query_tampering_is_ignored(): void
    {
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        [$financeUser] = $this->employeeIn($finance);
        [$itUser] = $this->employeeIn($it);

        $this->actingAs($financeUser)
            ->get(route('team-attendance.index', ['department_id' => $it->id]))
            ->assertOk()
            ->assertSee('Finance Team')
            ->assertDontSee('IT Team');
        $this->assertTrue(Gate::forUser($financeUser)->allows('viewTeamAttendance', $finance));
        $this->assertFalse(Gate::forUser($financeUser)->allows('viewTeamAttendance', $it));
        $this->assertTrue(Gate::forUser($itUser)->allows('viewTeamAttendance', $it));
        $this->assertFalse(Gate::forUser($itUser)->allows('viewTeamAttendance', $finance));

        $this->actingAs($financeUser)
            ->get(route('admin.departments.team-attendance.show', $it))
            ->assertForbidden();
    }

    public function test_employee_without_department_receives_safe_workspace_and_status_behavior(): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->create(['department_id' => null]);

        $this->actingAs($user)->get(route('team-attendance.index'))
            ->assertOk()
            ->assertSee('Team Attendance unavailable')
            ->assertSee('not assigned to a department');
        $this->actingAs($user)->getJson(route('team-attendance.status'))->assertNotFound();
    }

    public function test_hr_representative_access_is_independent_of_own_employee_department(): void
    {
        $humanResources = Department::factory()->create(['name' => 'Human Resources']);
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        [$representative] = $this->employeeIn($humanResources);
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();

        $this->actingAs($representative)->get(route('hr.team-attendance.index'))
            ->assertOk()
            ->assertSee('Finance Team')
            ->assertSee('HR Representative workspace');
        $this->assertTrue(Gate::forUser($representative)->allows('viewTeamAttendance', $humanResources));
        $this->assertTrue(Gate::forUser($representative)->allows('viewTeamAttendance', $finance));
        $this->assertFalse(Gate::forUser($representative)->allows('viewTeamAttendance', $it));

        $this->actingAs($representative)->get(route('employee.attendance.index'))->assertOk();
        $this->actingAs($representative)->post(route('employee.attendance.time-in'), [
            'work_arrangement' => WorkArrangement::OfficeBased->value,
        ])->assertRedirect();
        $this->assertSame(1, $representative->employee->attendanceSessions()->count());
    }

    public function test_unassigned_employee_cannot_open_hr_workspace(): void
    {
        [$employee] = $this->employeeIn(Department::factory()->create());

        $this->actingAs($employee)->get(route('hr.team-attendance.index'))->assertForbidden();
        $this->actingAs($employee)->getJson(route('hr.team-attendance.status'))->assertForbidden();
    }

    public function test_global_admin_can_list_and_open_every_department_workspace(): void
    {
        $admin = User::factory()->admin()->create();
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);

        $this->actingAs($admin)->get(route('admin.team-attendance.index'))
            ->assertOk()
            ->assertSee('Finance')
            ->assertSee('IT');
        $this->actingAs($admin)->get(route('admin.departments.team-attendance.show', $finance))
            ->assertOk()->assertSee('Finance Team');
        $this->actingAs($admin)->get(route('admin.departments.team-attendance.show', $it))
            ->assertOk()->assertSee('IT Team');
    }

    public function test_workspace_escapes_employee_names_and_omits_private_fields(): void
    {
        $department = Department::factory()->create();
        [$viewer] = $this->employeeIn($department);
        $dangerousName = '<script>alert("team")</script>';
        $privateEmail = 'private-team@example.test';
        $member = User::factory()->employee()->create(['name' => $dangerousName, 'email' => $privateEmail]);
        Employee::factory()->for($member)->for($department)->create();

        $this->actingAs($viewer)->get(route('team-attendance.index'))
            ->assertOk()
            ->assertSee($dangerousName)
            ->assertDontSee($dangerousName, false)
            ->assertDontSee($privateEmail);
    }

    public function test_workspace_marks_only_the_current_department_hr_representative_in_the_activity_feed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 18:00:00', 'Asia/Manila'));
        $department = Department::factory()->create(['name' => 'Finance']);
        [$viewer] = $this->employeeIn($department);
        [$representative, $hrEmployee] = $this->employeeIn($department);
        [$regularUser, $regularEmployee] = $this->employeeIn($department);
        DepartmentHrAssignment::factory()->for($department)->for($representative)->create();
        AttendanceSession::factory()->for($hrEmployee)->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'time_out_at' => '2026-09-24 16:56:00',
        ]);
        AttendanceSession::factory()->for($regularEmployee)->open()->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 09:15:00',
        ]);

        $response = $this->actingAs($viewer)->get(route('team-attendance.index'))
            ->assertOk()
            ->assertSee('Attendance thread')
            ->assertSee('HR Representative', false)
            ->assertSee($representative->name)
            ->assertSee($regularUser->name)
            ->assertDontSee('7.93 hrs worked')
            ->assertSee('Not Clocked In')
            ->assertDontSee('Team status');

        $this->assertSame(2, substr_count($response->getContent(), 'aria-label="HR Representative"'));
        $response->assertDontSee('message input')->assertDontSee('Send message');
    }

    public function test_workspace_omits_net_hours_only_from_the_thread_presentation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 18:00:00', 'Asia/Manila'));
        $department = Department::factory()->create();
        [$viewer] = $this->employeeIn($department);
        [, $employee] = $this->employeeIn($department);
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'time_out_at' => '2026-09-24 16:00:00',
            'work_arrangement' => WorkArrangement::WorkFromHome,
        ]);

        $response = $this->actingAs($viewer)->get(route('team-attendance.index'));

        $response->assertOk()
            ->assertSee('Timed out')
            ->assertSee('Work From Home')
            ->assertDontSee('7.00 hrs worked');
        $this->assertStringContainsString('"net_hours":"7.00 hrs"', $response->getContent());
    }

    /** @return array{User, Employee} */
    private function employeeIn(Department $department): array
    {
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->for($department)->create();

        return [$user, $employee];
    }
}
