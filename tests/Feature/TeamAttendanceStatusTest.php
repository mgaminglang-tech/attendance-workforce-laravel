<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Department;
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
        ]);
        AttendanceSession::factory()->for($completed)->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'time_out_at' => '2026-09-24 09:00:00',
        ]);

        $result = app(TeamAttendanceService::class)->forDepartment($department);
        $members = collect($result['members'])->keyBy('employee_name');

        $this->assertSame(['total' => 3, 'working' => 1, 'completed' => 1, 'not_clocked_in' => 1], $result['summary']);
        $this->assertSame('Working', $members['Working Employee']['status']);
        $this->assertSame('Sep 23, 2026', $members['Working Employee']['work_date']);
        $this->assertNull($members['Working Employee']['time_out']);
        $this->assertSame('Completed', $members['Completed Employee']['status']);
        $this->assertSame('Not clocked in', $members['Not Clocked In Employee']['status']);
        $this->assertNull($members['Not Clocked In Employee']['time_in']);
        $this->assertModelExists($notClockedIn);
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
        $this->assertSame('Finance Employee', $result['members'][0]['employee_name']);
        $this->assertSame('Completed', $result['members'][0]['status']);
        $this->assertNotContains('IT Private Employee', array_column($result['members'], 'employee_name'));
        $this->assertNotContains('Former Finance Employee', array_column($result['members'], 'employee_name'));
    }

    private function employee(Department $department, string $name): Employee
    {
        $user = User::factory()->employee()->create(['name' => $name]);

        return Employee::factory()->for($user)->for($department)->create();
    }
}
