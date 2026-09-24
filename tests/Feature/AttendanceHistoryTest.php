<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AttendanceHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_employee_attendance_status_prefers_an_overnight_open_session(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 07:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 23:30:00',
        ]);

        $this->actingAs($user)
            ->get(route('employee.attendance.index'))
            ->assertOk()
            ->assertSee('Currently working')
            ->assertSee('Sep 23, 2026 11:30:00 PM')
            ->assertSee('Time Out');
    }

    public function test_employee_attendance_status_shows_a_completed_current_work_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 18:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 08:00:00',
            'time_out_at' => '2026-09-23 17:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('employee.attendance.index'))
            ->assertOk()
            ->assertSee('Completed for today')
            ->assertDontSee(route('employee.attendance.time-in'), false);
    }

    public function test_employee_can_view_only_own_attendance_history(): void
    {
        [$user, $employee] = $this->activeEmployee();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-20',
            'time_in_at' => '2026-09-20 08:12:34',
            'time_out_at' => '2026-09-20 17:12:34',
        ]);
        AttendanceSession::factory()->create([
            'work_date' => '2026-09-21',
            'time_in_at' => '2026-09-21 06:54:32',
            'time_out_at' => '2026-09-21 15:54:32',
        ]);

        $this->actingAs($user)
            ->get(route('employee.attendance.history'))
            ->assertOk()
            ->assertSee('Sep 20, 2026 8:12:34 AM')
            ->assertDontSee('Sep 21, 2026 6:54:32 AM');
    }

    public function test_history_derives_duration_and_marks_open_sessions_without_persisting_totals(): void
    {
        [$user, $employee] = $this->activeEmployee();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-20',
            'time_in_at' => '2026-09-20 23:30:00',
            'time_out_at' => '2026-09-21 07:45:00',
        ]);
        AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-21',
            'time_in_at' => '2026-09-21 08:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('employee.attendance.history'))
            ->assertOk()
            ->assertSee('8h 15m')
            ->assertSee('Still working')
            ->assertSee('Working');

        $this->assertFalse(AttendanceSession::query()->firstOrFail()->isFillable('total_hours'));
    }

    public function test_history_paginates_and_orders_newest_work_date_first(): void
    {
        [$user, $employee] = $this->activeEmployee();
        $latestWorkDate = CarbonImmutable::parse('2026-09-23', 'Asia/Manila');

        foreach (range(0, 15) as $daysAgo) {
            $workDate = $latestWorkDate->subDays($daysAgo);
            AttendanceSession::factory()->for($employee)->create([
                'work_date' => $workDate->toDateString(),
                'time_in_at' => $workDate->setTime(8, 0),
                'time_out_at' => $workDate->setTime(17, 0),
            ]);
        }

        $this->actingAs($user)
            ->get(route('employee.attendance.history'))
            ->assertViewHas('attendanceSessions', function ($attendanceSessions): bool {
                return $attendanceSessions->total() === 16
                    && $attendanceSessions->perPage() === 15
                    && $attendanceSessions->first()->work_date->toDateString() === '2026-09-23'
                    && $attendanceSessions->last()->work_date->toDateString() === '2026-09-09';
            });
    }

    /** @return array{User, Employee} */
    private function activeEmployee(): array
    {
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();

        return [$user, $employee];
    }
}
