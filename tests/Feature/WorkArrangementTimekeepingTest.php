<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkArrangementTimekeepingTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('workArrangements')]
    public function test_employee_can_time_in_with_each_supported_work_arrangement(
        WorkArrangement $workArrangement,
        string $label,
        string $dtrLabel,
    ): void {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 08:01:02', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();

        $this->actingAs($user)->post(route('employee.attendance.time-in'), [
            'work_arrangement' => $workArrangement->value,
            'work_date' => '1999-01-01',
            'time_in_at' => '1999-01-01 00:00:00',
        ])->assertRedirect(route('employee.attendance.index'))
            ->assertSessionHas('status', 'You are now timed in.');

        $session = AttendanceSession::query()->whereBelongsTo($employee)->sole();
        $this->assertSame($workArrangement, $session->work_arrangement);
        $this->assertSame('2026-09-24', $session->work_date->toDateString());
        $this->assertSame('2026-09-24 08:01:02', $session->time_in_at->format('Y-m-d H:i:s'));
        $this->assertSame($label, $session->work_arrangement->label());
        $this->assertSame($dtrLabel, $session->work_arrangement->dtrLabel());
    }

    public function test_time_in_requires_supported_work_arrangement(): void
    {
        [$user] = $this->activeEmployee();

        $this->actingAs($user)->post(route('employee.attendance.time-in'))
            ->assertSessionHasErrors([
                'work_arrangement' => 'Select a Work Arrangement before timing in.',
            ]);
        $this->actingAs($user)->post(route('employee.attendance.time-in'), [
            'work_arrangement' => 'on_leave',
        ])->assertSessionHasErrors([
            'work_arrangement' => 'Select a valid Work Arrangement.',
        ]);

        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_time_out_preserves_arrangement_and_ignores_client_attempt_to_replace_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 17:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        $session = AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);

        $this->actingAs($user)->post(route('employee.attendance.time-out'), [
            'work_arrangement' => WorkArrangement::FieldBased->value,
            'attendance_session_id' => AttendanceSession::factory()->create()->id,
        ])->assertRedirect(route('employee.attendance.index'));

        $session->refresh();
        $this->assertSame(WorkArrangement::OfficeBased, $session->work_arrangement);
        $this->assertSame('2026-09-24 17:00:00', $session->time_out_at?->format('Y-m-d H:i:s'));
    }

    public function test_database_rejects_unsupported_non_null_work_arrangement(): void
    {
        $session = AttendanceSession::factory()->create([
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);

        $this->expectException(QueryException::class);

        DB::table('attendance_sessions')->where('id', $session->id)->update([
            'work_arrangement' => 'on_leave',
        ]);
    }

    public function test_employee_cannot_mutate_another_employees_work_arrangement(): void
    {
        [$user, $employee] = $this->activeEmployee();
        $ownSession = AttendanceSession::factory()->for($employee)->open()->create([
            'work_arrangement' => WorkArrangement::WorkFromHome,
        ]);
        $otherSession = AttendanceSession::factory()->open()->create([
            'work_arrangement' => WorkArrangement::FieldBased,
        ]);

        $this->actingAs($user)->post(route('employee.attendance.time-out'), [
            'attendance_session_id' => $otherSession->id,
            'work_arrangement' => WorkArrangement::OfficeBased->value,
        ])->assertRedirect();

        $this->assertNotNull($ownSession->refresh()->time_out_at);
        $this->assertSame(WorkArrangement::WorkFromHome, $ownSession->work_arrangement);
        $this->assertNull($otherSession->refresh()->time_out_at);
        $this->assertSame(WorkArrangement::FieldBased, $otherSession->work_arrangement);
        $this->assertFalse($ownSession->isFillable('work_arrangement'));
    }

    public function test_employee_pages_show_arrangement_and_render_legacy_null_neutrally(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Manila'));
        [$user, $employee] = $this->activeEmployee();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
            'time_out_at' => '2026-09-24 09:00:00',
            'work_arrangement' => WorkArrangement::FieldBased,
        ]);
        AttendanceSession::factory()->legacy()->for($employee)->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 08:00:00',
            'time_out_at' => '2026-09-23 17:00:00',
        ]);

        $this->actingAs($user)->get(route('employee.attendance.index'))
            ->assertOk()->assertSee('Field-Based');
        $this->actingAs($user)->get(route('employee.attendance.history'))
            ->assertOk()->assertSee('Field-Based')->assertSee('Not recorded');

        $this->assertNull(
            AttendanceSession::query()->whereDate('work_date', '2026-09-23')->sole()->work_arrangement,
        );
    }

    public function test_time_in_selector_is_mobile_friendly_and_not_shown_for_open_session(): void
    {
        [$user, $employee] = $this->activeEmployee();

        $this->actingAs($user)->get(route('employee.attendance.index'))
            ->assertOk()
            ->assertSee('Current server time')
            ->assertSee('data-manila-clock-time', false)
            ->assertSee('data-manila-clock-date', false)
            ->assertSee('name="work_arrangement"', false)
            ->assertSee('col-12 col-sm-4 work-arrangement-option', false)
            ->assertSee('Work From Home')
            ->assertSee('Office-Based')
            ->assertSee('Field-Based');

        AttendanceSession::factory()->for($employee)->open()->create([
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);

        $this->actingAs($user)->get(route('employee.attendance.index'))
            ->assertOk()
            ->assertSee('Office-Based')
            ->assertSee('btn btn-workforce btn-lg attendance-action', false)
            ->assertDontSee('btn btn-danger btn-lg attendance-action', false)
            ->assertDontSee('name="work_arrangement"', false);
    }

    /** @return array<string, array{WorkArrangement, string, string}> */
    public static function workArrangements(): array
    {
        return [
            'work from home' => [WorkArrangement::WorkFromHome, 'Work From Home', 'WFH'],
            'office based' => [WorkArrangement::OfficeBased, 'Office-Based', 'OFFICE-BASED'],
            'field based' => [WorkArrangement::FieldBased, 'Field-Based', 'FIELD-BASED'],
        ];
    }

    /** @return array{User, Employee} */
    private function activeEmployee(): array
    {
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();

        return [$user, $employee];
    }
}
