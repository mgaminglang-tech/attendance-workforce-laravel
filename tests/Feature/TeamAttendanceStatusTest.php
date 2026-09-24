<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use App\Services\TeamAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TeamAttendanceStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_team_statuses_use_open_then_current_manila_work_date_semantics(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Manila'));
        $department = Department::factory()->create();
        $working = $this->employee($department, 'Working Employee');
        $completed = $this->employee($department, 'Completed Employee');
        $notClockedIn = $this->employee($department, 'Not Clocked In Employee');
        AttendanceSession::factory()->for($working)->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 22:00:00',
            'work_arrangement' => WorkArrangement::WorkFromHome,
        ]);
        AttendanceSession::factory()->for($completed)->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'time_out_at' => '2026-09-24 09:00:00',
            'work_arrangement' => WorkArrangement::FieldBased,
        ]);

        $result = app(TeamAttendanceService::class)->forDepartment($department);
        $activity = collect($result['activity']);

        $this->assertSame(['total' => 3, 'working' => 1, 'completed' => 1, 'not_clocked_in' => 1], $result['summary']);
        $this->assertSame('Timed in', $activity->firstWhere('employee_name', 'Working Employee')['event']);
        $this->assertSame('Work From Home', $activity->firstWhere('employee_name', 'Working Employee')['work_arrangement']);
        $this->assertSame('Timed out', $activity->firstWhere('employee_name', 'Completed Employee')['event']);
        $this->assertSame('Field-Based', $activity->firstWhere('employee_name', 'Completed Employee')['work_arrangement']);
        $this->assertSame('Not Clocked In', $activity->firstWhere('employee_name', 'Not Clocked In Employee')['event']);
        $this->assertNull($activity->firstWhere('employee_name', 'Not Clocked In Employee')['work_arrangement']);
        $this->assertNull($activity->firstWhere('employee_name', 'Not Clocked In Employee')['event_time']);
        $this->assertModelExists($notClockedIn);
    }

    public function test_legacy_team_attendance_session_displays_not_recorded_arrangement(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Manila'));
        $department = Department::factory()->create();
        $legacyEmployee = $this->employee($department, 'Legacy Employee');
        AttendanceSession::factory()->legacy()->for($legacyEmployee)->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 22:00:00',
        ]);

        $result = app(TeamAttendanceService::class)->forDepartment($department);

        $this->assertSame('Not recorded', $result['activity'][0]['work_arrangement']);
    }

    public function test_activity_feed_contains_time_events_net_hours_initials_and_only_the_assigned_hr_badge(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 18:00:00', 'Asia/Manila'));
        $department = Department::factory()->create();
        $hrEmployee = $this->employee($department, 'Mervin Gaa');
        $regularEmployee = $this->employee($department, 'Juan Dela Cruz');
        DepartmentHrAssignment::factory()->for($department)->for($hrEmployee->user)->create();
        AttendanceSession::factory()->for($hrEmployee)->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'time_out_at' => '2026-09-24 16:56:00',
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);
        AttendanceSession::factory()->for($regularEmployee)->open()->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 09:15:00',
            'work_arrangement' => WorkArrangement::WorkFromHome,
        ]);

        $activity = collect(app(TeamAttendanceService::class)->forDepartment($department)['activity']);
        $hrEvents = $activity->where('employee_name', 'Mervin Gaa');
        $regularEvents = $activity->where('employee_name', 'Juan Dela Cruz');

        $this->assertSame(['Timed out', 'Timed in'], $hrEvents->pluck('event')->values()->all());
        $this->assertSame('MG', $hrEvents->first()['employee_initials']);
        $this->assertTrue($hrEvents->every('is_hr_representative'));
        $this->assertSame('7.93 hrs', $hrEvents->firstWhere('event', 'Timed out')['net_hours']);
        $this->assertSame('JD', $regularEvents->first()['employee_initials']);
        $this->assertTrue($regularEvents->every(fn (array $event): bool => ! $event['is_hr_representative']));
        $this->assertSame('Today', $activity->first()['date_label']);
    }

    public function test_other_department_and_disabled_employee_attendance_does_not_leak_or_break_workspace(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Manila'));
        $finance = Department::factory()->create();
        $it = Department::factory()->create();
        $financeEmployee = $this->employee($finance, 'Finance Employee');
        $itEmployee = $this->employee($it, 'IT Private Employee');
        $disabledUser = User::factory()->employee()->disabled()->create(['name' => 'Former Finance Employee']);
        $disabledEmployee = Employee::factory()->for($disabledUser)->for($finance)->create();
        AttendanceSession::factory()->for($financeEmployee)->create(['work_date' => '2026-09-24']);
        AttendanceSession::factory()->for($itEmployee)->open()->create(['work_date' => '2026-09-23']);
        AttendanceSession::factory()->for($disabledEmployee)->create(['work_date' => '2026-09-24']);

        $result = app(TeamAttendanceService::class)->forDepartment($finance);

        $this->assertSame(1, $result['summary']['total']);
        $this->assertSame('Finance Employee', $result['activity'][0]['employee_name']);
        $this->assertSame('Timed out', $result['activity'][0]['event']);
        $this->assertNotContains('IT Private Employee', array_column($result['activity'], 'employee_name'));
        $this->assertNotContains('Former Finance Employee', array_column($result['activity'], 'employee_name'));
    }

    private function employee(Department $department, string $name): Employee
    {
        $user = User::factory()->employee()->create(['name' => $name]);

        return Employee::factory()->for($user)->for($department)->create();
    }
}
