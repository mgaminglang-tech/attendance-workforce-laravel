<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EmployeeLeaveTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_employee_records_an_inclusive_calendar_range_for_only_their_own_profile(): void
    {
        [$user, $employee] = $this->activeEmployee();
        $otherEmployee = Employee::factory()->create();

        $this->actingAs($user)->post(route('employee.attendance.leave.store'), [
            'from_date' => '2026-09-25',
            'to_date' => '2026-09-28',
            'employee_id' => $otherEmployee->getKey(),
        ])->assertRedirect(route('employee.attendance.index'))
            ->assertSessionHas('status', 'Leave recorded for 4 days.');

        $this->assertSame(
            ['2026-09-25', '2026-09-26', '2026-09-27', '2026-09-28'],
            $employee->leaveDays()->orderBy('leave_date')->pluck('leave_date')->map->toDateString()->all(),
        );
        $this->assertSame(0, $otherEmployee->leaveDays()->count());
    }

    public function test_leave_date_range_is_validated(): void
    {
        [$user] = $this->activeEmployee();

        $this->actingAs($user)->post(route('employee.attendance.leave.store'), [
            'from_date' => 'not-a-date',
            'to_date' => '2026-09-24',
        ])->assertSessionHasErrors(['from_date', 'to_date']);

        $this->actingAs($user)->post(route('employee.attendance.leave.store'), [
            'from_date' => '2026-09-26',
            'to_date' => '2026-09-25',
        ])->assertSessionHasErrors(['to_date']);

        $this->assertDatabaseCount('employee_leave_days', 0);
    }

    public function test_duplicate_leave_rejects_the_whole_range_atomically(): void
    {
        [$user, $employee] = $this->activeEmployee();
        EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-26']);

        $this->actingAs($user)->post(route('employee.attendance.leave.store'), [
            'from_date' => '2026-09-25',
            'to_date' => '2026-09-27',
        ])->assertSessionHasErrors([
            'leave_record' => 'Leave has already been recorded for one or more selected dates.',
        ]);

        $this->assertSame(['2026-09-26'], $employee->leaveDays()->pluck('leave_date')->map->toDateString()->all());
    }

    public function test_attendance_conflict_rejects_the_whole_range_atomically(): void
    {
        [$user, $employee] = $this->activeEmployee();
        AttendanceSession::factory()->for($employee)->create(['work_date' => '2026-09-26']);

        $this->actingAs($user)->post(route('employee.attendance.leave.store'), [
            'from_date' => '2026-09-25',
            'to_date' => '2026-09-27',
        ])->assertSessionHasErrors([
            'leave_record' => 'Leave cannot be recorded for a date that already has attendance.',
        ]);

        $this->assertDatabaseCount('employee_leave_days', 0);
    }

    public function test_today_leave_blocks_time_in_until_the_employee_removes_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-25 08:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        $leaveDay = EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-25']);

        $this->actingAs($user)->post(route('employee.attendance.time-in'), [
            'work_arrangement' => WorkArrangement::OfficeBased->value,
        ])->assertSessionHasErrors(['attendance' => 'You recorded today as leave.']);
        $this->assertDatabaseCount('attendance_sessions', 0);

        $this->actingAs($user)->delete(route('employee.attendance.leave.destroy', $leaveDay))
            ->assertRedirect(route('employee.attendance.index'))
            ->assertSessionHas('status', 'Leave record removed.');

        $this->actingAs($user)->post(route('employee.attendance.time-in'), [
            'work_arrangement' => WorkArrangement::OfficeBased->value,
        ])->assertSessionHas('status', 'You are now timed in.');
        $this->assertDatabaseCount('attendance_sessions', 1);
    }

    public function test_employee_cannot_remove_another_employees_leave_or_past_leave(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-25 08:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        $otherLeave = EmployeeLeaveDay::factory()->create(['leave_date' => '2026-09-26']);
        $pastLeave = EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-24']);

        $this->actingAs($user)->delete(route('employee.attendance.leave.destroy', $otherLeave))->assertNotFound();
        $this->actingAs($user)->delete(route('employee.attendance.leave.destroy', $pastLeave))
            ->assertSessionHasErrors(['leave_remove' => 'Past leave records cannot be removed.']);

        $this->assertModelExists($otherLeave);
        $this->assertModelExists($pastLeave);
    }

    public function test_inactive_employee_cannot_record_leave(): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->inactive()->for($user)->create();

        $this->actingAs($user)->post(route('employee.attendance.leave.store'), [
            'from_date' => '2026-09-25',
            'to_date' => '2026-09-25',
        ])->assertSessionHasErrors([
            'leave_record' => 'Your employment status does not permit leave actions.',
        ]);
        $this->assertDatabaseCount('employee_leave_days', 0);
    }

    public function test_attendance_page_shows_leave_controls_upcoming_records_and_today_status(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-25 08:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-25']);
        EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-28']);

        $this->actingAs($user)->get(route('employee.attendance.index'))
            ->assertOk()
            ->assertSee('Record Leave')
            ->assertSee('type="date"', false)
            ->assertSee('data-bs-target="#record-leave-modal"', false)
            ->assertSee('Current and upcoming leave')
            ->assertSee('Today')
            ->assertSee('Mon, Sep 28')
            ->assertSee('On Leave')
            ->assertSee('You recorded today as leave.')
            ->assertDontSee(route('employee.attendance.time-in'), false);
    }

    public function test_database_enforces_unique_leave_dates_and_restricts_employee_deletion(): void
    {
        $employee = Employee::factory()->create();
        EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-25']);

        try {
            EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-25']);
            $this->fail('Expected the unique employee leave date constraint to reject the duplicate.');
        } catch (QueryException) {
            $this->assertSame(1, $employee->leaveDays()->count());
        }

        $this->expectException(QueryException::class);
        $employee->delete();
    }

    /** @return array{User, Employee} */
    private function activeEmployee(): array
    {
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();

        return [$user, $employee];
    }
}
