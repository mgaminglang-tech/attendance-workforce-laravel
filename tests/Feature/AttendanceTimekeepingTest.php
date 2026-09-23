<?php

namespace Tests\Feature;

use App\Actions\Attendance\TimeInEmployee;
use App\Actions\Attendance\TimeOutEmployee;
use App\Enums\AccountStatus;
use App\Exceptions\AttendanceActionException;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AttendanceTimekeepingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_employee_can_time_in_using_server_generated_timestamp_only(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 23:30:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();

        $this->actingAs($user)->post(route('employee.attendance.time-in'), [
            'work_date' => '1999-01-01',
            'time_in_at' => '1999-01-01 00:00:00',
            'employee_id' => Employee::factory()->create()->id,
        ])->assertRedirect(route('employee.attendance.index'))
            ->assertSessionHas('status', 'You are now timed in.');

        $attendanceSession = AttendanceSession::query()->whereBelongsTo($employee)->firstOrFail();
        $this->assertSame('2026-09-23', $attendanceSession->work_date->toDateString());
        $this->assertSame('2026-09-23 23:30:00', $attendanceSession->time_in_at->format('Y-m-d H:i:s'));
        $this->assertNull($attendanceSession->time_out_at);
        $this->assertDatabaseMissing('attendance_sessions', [
            'work_date' => '1999-01-01',
        ]);
    }

    public function test_work_date_is_derived_from_server_time_in_asia_manila(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 16:30:00', 'UTC'));
        [$user, $employee] = $this->activeEmployee();

        $attendanceSession = $this->app->make(TimeInEmployee::class)->handle($user);

        $this->assertTrue($attendanceSession->employee->is($employee));
        $this->assertSame('2026-09-24', $attendanceSession->work_date->toDateString());
        $this->assertSame('2026-09-24 00:30:00', $attendanceSession->time_in_at->format('Y-m-d H:i:s'));
    }

    public function test_employee_cannot_time_in_twice_while_already_clocked_in(): void
    {
        [$user] = $this->activeEmployee();

        $this->actingAs($user)->post(route('employee.attendance.time-in'));

        $this->actingAs($user)
            ->post(route('employee.attendance.time-in'))
            ->assertRedirect(route('employee.attendance.index'))
            ->assertSessionHasErrors([
                'attendance' => 'You are already timed in.',
            ]);
        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_employee_cannot_start_a_second_session_for_a_completed_work_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 17:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 08:00:00',
            'time_out_at' => '2026-09-23 16:00:00',
        ]);

        $this->actingAs($user)
            ->post(route('employee.attendance.time-in'))
            ->assertSessionHasErrors([
                'attendance' => 'Your attendance for today has already been completed.',
            ]);
        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_database_unique_constraint_prevents_raced_duplicate_work_date(): void
    {
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->create(['work_date' => '2026-09-23']);

        $this->expectException(QueryException::class);

        AttendanceSession::factory()->for($employee)->create(['work_date' => '2026-09-23']);
    }

    public function test_active_employee_can_time_out_using_server_generated_timestamp_only(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 07:30:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        $attendanceSession = AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 23:30:00',
        ]);

        $this->actingAs($user)->post(route('employee.attendance.time-out'), [
            'time_out_at' => '2099-12-31 23:59:59',
            'attendance_session_id' => AttendanceSession::factory()->create()->id,
        ])->assertRedirect(route('employee.attendance.index'))
            ->assertSessionHas('status', 'You are now timed out.');

        $attendanceSession->refresh();
        $this->assertSame('2026-09-23', $attendanceSession->work_date->toDateString());
        $this->assertSame('2026-09-24 07:30:00', $attendanceSession->time_out_at?->format('Y-m-d H:i:s'));
    }

    public function test_time_out_closes_only_the_authenticated_employees_open_session(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 17:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        $ownSession = AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 08:00:00',
        ]);
        $otherSession = AttendanceSession::factory()->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 09:00:00',
        ]);

        $this->app->make(TimeOutEmployee::class)->handle($user);

        $this->assertSame('2026-09-23 17:00:00', $ownSession->refresh()->time_out_at?->format('Y-m-d H:i:s'));
        $this->assertNull($otherSession->refresh()->time_out_at);
    }

    public function test_employee_cannot_time_out_without_an_open_session_or_twice(): void
    {
        [$user, $employee] = $this->activeEmployee();
        $this->actingAs($user)
            ->post(route('employee.attendance.time-out'))
            ->assertSessionHasErrors(['attendance' => 'No active attendance session was found.']);

        AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => now()->toDateString(),
            'time_in_at' => now()->subHour(),
        ]);
        $this->actingAs($user)->post(route('employee.attendance.time-out'));

        $this->actingAs($user)
            ->post(route('employee.attendance.time-out'))
            ->assertSessionHasErrors(['attendance' => 'No active attendance session was found.']);
        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_overnight_time_out_preserves_the_original_work_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 23:30:00', 'Asia/Manila'));
        [$user] = $this->activeEmployee();
        $attendanceSession = $this->app->make(TimeInEmployee::class)->handle($user);
        $this->travelTo(CarbonImmutable::parse('2026-09-24 07:30:00', 'Asia/Manila'));

        $closedSession = $this->app->make(TimeOutEmployee::class)->handle($user);

        $this->assertTrue($closedSession->is($attendanceSession));
        $this->assertSame('2026-09-23', $closedSession->work_date->toDateString());
        $this->assertSame(480, $closedSession->workedMinutes());
    }

    public function test_time_out_cannot_precede_time_in(): void
    {
        [$user, $employee] = $this->activeEmployee();
        $attendanceSession = AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 09:00:00',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:59:59', 'Asia/Manila'));

        try {
            $this->app->make(TimeOutEmployee::class)->handle($user);
            $this->fail('Expected an earlier Time Out to be rejected.');
        } catch (AttendanceActionException $exception) {
            $this->assertSame('Time Out cannot be earlier than Time In.', $exception->getMessage());
            $this->assertNull($attendanceSession->refresh()->time_out_at);
        }
    }

    public function test_historical_attendance_remains_after_account_disable(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $employee] = $this->activeEmployee();
        $attendanceSession = AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-22',
        ]);

        $this->actingAs($admin)->patch(route('admin.employees.account.update', $employee), [
            'account_status' => AccountStatus::Disabled->value,
        ])->assertRedirect();

        $this->assertSame(AccountStatus::Disabled, $user->refresh()->account_status);
        $this->assertModelExists($attendanceSession);
        $this->assertTrue($attendanceSession->refresh()->employee->is($employee));
    }

    public function test_employee_with_attendance_history_cannot_be_deleted(): void
    {
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->create();

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
