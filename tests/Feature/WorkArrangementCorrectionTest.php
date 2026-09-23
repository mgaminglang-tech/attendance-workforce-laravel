<?php

namespace Tests\Feature;

use App\Actions\Attendance\CorrectAttendanceSession;
use App\Enums\WorkArrangement;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class WorkArrangementCorrectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_correct_arrangement_only_and_audit_preserves_before_and_after(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 18:30:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        $session = $this->attendanceSession(WorkArrangement::WorkFromHome);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            ...$this->unchangedTimes($session),
            'work_arrangement' => WorkArrangement::OfficeBased->value,
            'reason' => 'Supervisor verified the office attendance log.',
        ])->assertRedirect(route('admin.attendance.show', $session));

        $session->refresh();
        $adjustment = AttendanceAdjustment::sole();
        $this->assertSame(WorkArrangement::OfficeBased, $session->work_arrangement);
        $this->assertSame(WorkArrangement::WorkFromHome, $adjustment->before_work_arrangement);
        $this->assertSame(WorkArrangement::OfficeBased, $adjustment->after_work_arrangement);
        $this->assertSame($session->time_in_at->format('Y-m-d H:i:s'), $adjustment->corrected_time_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-24 18:30:00', $adjustment->corrected_at->format('Y-m-d H:i:s'));
    }

    public function test_legacy_null_can_be_preserved_or_replaced_with_known_arrangement(): void
    {
        $admin = User::factory()->admin()->create();
        $session = $this->attendanceSession(null);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            'time_in_at' => '2026-09-23T08:15:00',
            'time_out_at' => '2026-09-23T17:00:00',
            'work_arrangement' => '',
            'reason' => 'Verified corrected Time In from the paper log.',
        ])->assertRedirect();

        $firstAdjustment = AttendanceAdjustment::sole();
        $this->assertNull($session->refresh()->work_arrangement);
        $this->assertNull($firstAdjustment->before_work_arrangement);
        $this->assertNull($firstAdjustment->after_work_arrangement);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            ...$this->unchangedTimes($session),
            'work_arrangement' => WorkArrangement::FieldBased->value,
            'reason' => 'Field supervisor confirmed the historical assignment.',
        ])->assertRedirect();

        $secondAdjustment = AttendanceAdjustment::query()->latest('id')->firstOrFail();
        $this->assertSame(WorkArrangement::FieldBased, $session->refresh()->work_arrangement);
        $this->assertNull($secondAdjustment->before_work_arrangement);
        $this->assertSame(WorkArrangement::FieldBased, $secondAdjustment->after_work_arrangement);
    }

    public function test_unsupported_or_cleared_arrangement_is_rejected_without_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $session = $this->attendanceSession(WorkArrangement::OfficeBased);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            ...$this->unchangedTimes($session),
            'work_arrangement' => 'leave_without_pay',
            'reason' => 'This unsupported value must be rejected.',
        ])->assertSessionHasErrors('work_arrangement');
        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            ...$this->unchangedTimes($session),
            'work_arrangement' => '',
            'reason' => 'A recorded arrangement must not be cleared.',
        ])->assertSessionHasErrors([
            'work_arrangement' => 'A recorded Work Arrangement cannot be cleared.',
        ]);

        $this->assertSame(WorkArrangement::OfficeBased, $session->refresh()->work_arrangement);
        $this->assertDatabaseCount('attendance_adjustments', 0);
    }

    public function test_arrangement_correction_still_requires_meaningful_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $session = $this->attendanceSession(WorkArrangement::WorkFromHome);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            ...$this->unchangedTimes($session),
            'work_arrangement' => WorkArrangement::FieldBased->value,
            'reason' => '',
        ])->assertSessionHasErrors([
            'reason' => 'Correction reason is required.',
        ]);

        $this->assertSame(WorkArrangement::WorkFromHome, $session->refresh()->work_arrangement);
        $this->assertDatabaseCount('attendance_adjustments', 0);
    }

    public function test_hr_representative_and_employee_cannot_access_admin_arrangement_correction(): void
    {
        $department = Department::factory()->create();
        $representative = User::factory()->employee()->create();
        Employee::factory()->for($representative)->create();
        DepartmentHrAssignment::factory()->for($department)->for($representative)->create();
        $session = $this->attendanceSession(WorkArrangement::WorkFromHome);

        $this->actingAs($representative)
            ->get(route('admin.attendance.correction.edit', $session))
            ->assertForbidden();
        $this->actingAs($representative)->put(route('admin.attendance.correction.update', $session), [
            ...$this->unchangedTimes($session),
            'work_arrangement' => WorkArrangement::OfficeBased->value,
            'reason' => 'Unauthorized arrangement correction attempt.',
        ])->assertForbidden();

        $this->assertSame(WorkArrangement::WorkFromHome, $session->refresh()->work_arrangement);
        $this->assertDatabaseCount('attendance_adjustments', 0);
    }

    public function test_admin_views_display_recorded_and_legacy_arrangements(): void
    {
        $admin = User::factory()->admin()->create();
        $recorded = $this->attendanceSession(WorkArrangement::OfficeBased);
        $legacy = AttendanceSession::factory()->legacy()->create();

        $this->actingAs($admin)->get(route('admin.attendance.index'))
            ->assertOk()->assertSee('Office-Based')->assertSee('Not recorded');
        $this->actingAs($admin)->get(route('admin.attendance.show', $recorded))
            ->assertOk()->assertSee('Office-Based');
        $this->actingAs($admin)->get(route('admin.attendance.show', $legacy))
            ->assertOk()->assertSee('Not recorded');
    }

    public function test_arrangement_and_audit_write_roll_back_atomically_when_audit_insert_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $session = $this->attendanceSession(WorkArrangement::WorkFromHome);
        DB::table('users')->where('id', $admin->id)->delete();

        try {
            app(CorrectAttendanceSession::class)->handle(
                $admin,
                $session,
                $session->time_in_at,
                $session->time_out_at,
                WorkArrangement::OfficeBased,
                'The audit foreign key must fail this correction.',
            );
            $this->fail('The missing administrator must reject the audit insert.');
        } catch (QueryException) {
            $this->assertSame(WorkArrangement::WorkFromHome, $session->refresh()->work_arrangement);
            $this->assertDatabaseCount('attendance_adjustments', 0);
        }
    }

    public function test_arrangement_audit_snapshot_remains_immutable(): void
    {
        $adjustment = AttendanceAdjustment::factory()->create();

        $this->expectException(LogicException::class);
        $adjustment->forceFill([
            'after_work_arrangement' => WorkArrangement::FieldBased,
        ])->save();
    }

    private function attendanceSession(?WorkArrangement $workArrangement): AttendanceSession
    {
        return AttendanceSession::factory()->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 08:00:00',
            'time_out_at' => '2026-09-23 17:00:00',
            'work_arrangement' => $workArrangement,
        ]);
    }

    /** @return array{time_in_at: string, time_out_at: string} */
    private function unchangedTimes(AttendanceSession $session): array
    {
        return [
            'time_in_at' => $session->time_in_at->format('Y-m-d\TH:i:s'),
            'time_out_at' => $session->time_out_at?->format('Y-m-d\TH:i:s'),
        ];
    }
}
