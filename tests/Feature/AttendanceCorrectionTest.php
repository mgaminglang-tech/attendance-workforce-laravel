<?php

namespace Tests\Feature;

use App\Actions\Attendance\CorrectAttendanceSession;
use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_correction_updates_canonical_session_and_records_exact_attributed_snapshot(): void
    {
        CarbonImmutable::setTestNow('2026-09-23 18:30:00');
        $admin = User::factory()->admin()->create();
        $session = AttendanceSession::factory()->create([
            'work_date' => '2026-09-22',
            'time_in_at' => $this->manila('2026-09-22 08:00:00'),
            'time_out_at' => $this->manila('2026-09-22 17:00:00'),
        ]);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            'time_in_at' => '2026-09-22T08:15:00',
            'time_out_at' => '2026-09-22T17:30:00',
            'reason' => '  Verified against the signed supervisor log.  ',
        ])->assertRedirect(route('admin.attendance.show', $session))->assertSessionHas('status');

        $session->refresh();
        $this->assertSame('2026-09-22', $session->work_date->toDateString());
        $this->assertTrue($session->time_in_at->equalTo($this->manila('2026-09-22 08:15:00')));
        $this->assertTrue($session->time_out_at->equalTo($this->manila('2026-09-22 17:30:00')));

        $adjustment = AttendanceAdjustment::sole();
        $this->assertTrue($adjustment->administrator->is($admin));
        $this->assertSame('Verified against the signed supervisor log.', $adjustment->reason);
        $this->assertSame('2026-09-22', $adjustment->previous_work_date->toDateString());
        $this->assertTrue($adjustment->previous_time_in_at->equalTo($this->manila('2026-09-22 08:00:00')));
        $this->assertTrue($adjustment->corrected_time_out_at->equalTo($this->manila('2026-09-22 17:30:00')));
        $this->assertTrue($adjustment->corrected_at->equalTo(now(config('app.timezone'))));
    }

    public function test_admin_can_close_open_session_for_disabled_or_inactive_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $disabledUser = User::factory()->employee()->disabled()->create();
        $employee = Employee::factory()->for($disabledUser)->create(['employment_status' => EmploymentStatus::Inactive]);
        $session = AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-22',
            'time_in_at' => $this->manila('2026-09-22 21:00:00'),
        ]);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            'time_in_at' => '2026-09-22T21:00:00',
            'time_out_at' => '2026-09-23T05:00:00',
            'reason' => 'Supervisor confirmed the overnight shift end.',
        ])->assertRedirect(route('admin.attendance.show', $session));

        $this->assertTrue($session->refresh()->time_out_at->equalTo($this->manila('2026-09-23 05:00:00')));
        $this->assertSame('2026-09-22', $session->work_date->toDateString());
    }

    public function test_changing_time_in_derives_work_date_and_rejects_an_existing_date_collision(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $session = AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-20',
            'time_in_at' => $this->manila('2026-09-20 08:00:00'),
            'time_out_at' => $this->manila('2026-09-20 17:00:00'),
        ]);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            'time_in_at' => '2026-09-21T08:00:00',
            'time_out_at' => '2026-09-21T17:00:00',
            'reason' => 'Verified that the shift occurred the next day.',
        ])->assertRedirect(route('admin.attendance.show', $session));
        $this->assertSame('2026-09-21', $session->refresh()->work_date->toDateString());

        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-22',
            'time_in_at' => $this->manila('2026-09-22 08:00:00'),
        ]);

        $this->actingAs($admin)->from(route('admin.attendance.correction.edit', $session))
            ->put(route('admin.attendance.correction.update', $session), [
                'time_in_at' => '2026-09-22T09:00:00',
                'time_out_at' => '2026-09-22T18:00:00',
                'reason' => 'Attempted correction after checking another log.',
            ])->assertRedirect(route('admin.attendance.correction.edit', $session))
            ->assertSessionHasErrors('time_in_at');

        $this->assertSame('2026-09-21', $session->refresh()->work_date->toDateString());
        $this->assertDatabaseCount('attendance_adjustments', 1);
    }

    public function test_invalid_noop_or_unmeaningful_corrections_do_not_mutate_or_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $session = AttendanceSession::factory()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => $this->manila('2026-09-23 08:00:00'),
            'time_out_at' => $this->manila('2026-09-23 17:00:00'),
        ]);

        foreach ([
            ['2026-09-23T09:00:00', '2026-09-23T18:00:00', ''],
            ['2026-09-23T08:00:00', '2026-09-23T17:00:00', 'No actual value changed.'],
            ['2026-09-23T09:00:00', '2026-09-23T09:00:00', 'Verified but invalid chronology.'],
            ['2026-09-23T09:00:00', '2026-09-23T18:00:00', '----------'],
        ] as [$timeIn, $timeOut, $reason]) {
            $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
                'time_in_at' => $timeIn,
                'time_out_at' => $timeOut,
                'reason' => $reason,
            ])->assertSessionHasErrors();
        }

        $this->assertTrue($session->refresh()->time_in_at->equalTo($this->manila('2026-09-23 08:00:00')));
        $this->assertDatabaseCount('attendance_adjustments', 0);
    }

    public function test_correction_never_reassigns_session_owner_and_reason_is_escaped_in_history(): void
    {
        $admin = User::factory()->admin()->create();
        $session = AttendanceSession::factory()->create();
        $otherEmployee = Employee::factory()->create();
        $originalEmployeeId = $session->employee_id;
        $reason = '<script>alert("audit")</script> verified by supervisor';

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            'employee_id' => $otherEmployee->id,
            'time_in_at' => $session->time_in_at->addMinute()->format('Y-m-d\TH:i:s'),
            'time_out_at' => $session->time_out_at->addMinute()->format('Y-m-d\TH:i:s'),
            'reason' => $reason,
        ])->assertRedirect();

        $this->assertSame($originalEmployeeId, $session->refresh()->employee_id);
        $this->actingAs($admin)->get(route('admin.attendance.show', $session))
            ->assertOk()->assertSee($reason)->assertDontSee($reason, false);
    }

    public function test_multiple_corrections_preserve_complete_chronological_immutable_history(): void
    {
        $admin = User::factory()->admin()->create();
        $session = AttendanceSession::factory()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => $this->manila('2026-09-23 08:00:00'),
            'time_out_at' => null,
        ]);
        $action = app(CorrectAttendanceSession::class);

        CarbonImmutable::setTestNow('2026-09-23 18:00:00');
        $first = $action->handle($admin, $session, $this->manila('2026-09-23 08:00:00'), $this->manila('2026-09-23 17:00:00'), $session->work_arrangement, 'First verified correction.');
        CarbonImmutable::setTestNow('2026-09-23 19:00:00');
        $second = $action->handle($admin, $session, $this->manila('2026-09-23 08:15:00'), $this->manila('2026-09-23 17:00:00'), $session->work_arrangement, 'Second verified correction.');

        $this->assertTrue($first->corrected_time_out_at->equalTo($second->previous_time_out_at));
        $this->assertSame([$first->id, $second->id], $session->adjustments()->orderBy('corrected_at')->pluck('id')->all());

        $this->expectException(LogicException::class);
        $first->forceFill(['reason' => 'Tampered history value.'])->save();
    }

    public function test_adjustment_cannot_be_deleted_and_foreign_keys_restrict_parent_deletion(): void
    {
        $adjustment = AttendanceAdjustment::factory()->create();

        try {
            $adjustment->delete();
            $this->fail('Deleting an attendance adjustment should fail.');
        } catch (LogicException) {
            $this->assertDatabaseHas('attendance_adjustments', ['id' => $adjustment->id]);
        }

        try {
            $adjustment->attendanceSession->delete();
            $this->fail('Deleting audited attendance should fail.');
        } catch (QueryException) {
            $this->assertDatabaseHas('attendance_adjustments', ['id' => $adjustment->id]);
        }

        $employeeUser = $adjustment->attendanceSession->employee->user;
        $employeeUser->update(['account_status' => AccountStatus::Disabled]);
        $this->assertDatabaseHas('attendance_adjustments', ['id' => $adjustment->id]);

        $this->expectException(QueryException::class);
        $adjustment->administrator->delete();
    }

    public function test_action_rejects_non_admin_and_rolls_back_session_if_audit_insert_fails(): void
    {
        $employeeUser = User::factory()->employee()->create();
        $session = AttendanceSession::factory()->create();

        try {
            app(CorrectAttendanceSession::class)->handle(
                $employeeUser,
                $session,
                $session->time_in_at->addMinute(),
                $session->time_out_at?->addMinute(),
                $session->work_arrangement,
                'Unauthorized correction attempt.',
            );
            $this->fail('An employee should not authorize the correction action.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('attendance_adjustments', 0);
        }

        $admin = User::factory()->admin()->create();
        DB::table('users')->where('id', $admin->id)->delete();
        $originalTimeIn = $session->time_in_at;

        try {
            app(CorrectAttendanceSession::class)->handle(
                $admin,
                $session,
                $session->time_in_at->addMinutes(5),
                $session->time_out_at,
                $session->work_arrangement,
                'This audit insert must fail atomically.',
            );
            $this->fail('The audit foreign key should reject the missing administrator.');
        } catch (QueryException) {
            $this->assertTrue($session->refresh()->time_in_at->equalTo($originalTimeIn));
            $this->assertDatabaseCount('attendance_adjustments', 0);
        }
    }

    private function manila(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'));
    }
}
