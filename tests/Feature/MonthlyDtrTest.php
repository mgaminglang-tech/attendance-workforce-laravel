<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use App\Services\BuildMonthlyDtr;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthlyDtrTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('calendarMonths')]
    public function test_builds_every_calendar_date_and_certification_boundary(
        string $month,
        int $expectedRows,
        string $expectedLastDate,
        string $expectedLastLabel,
    ): void {
        $dtr = $this->build(Employee::factory()->create(), $month);

        $this->assertCount($expectedRows, $dtr['rows']);
        $this->assertSame("{$month}-01", $dtr['rows'][0]['date']->toDateString());
        $this->assertSame($expectedLastDate, $dtr['rows'][$expectedRows - 1]['date']->toDateString());
        $this->assertSame(mb_strtoupper(CarbonImmutable::parse("{$month}-01")->format('F d, Y')), $dtr['first_date_label']);
        $this->assertSame($expectedLastLabel, $dtr['last_date_label']);
    }

    #[DataProvider('completedSessionDurations')]
    public function test_completed_attendance_deducts_the_fixed_break(
        string $timeIn,
        string $timeOut,
        string $expectedTotalHours,
    ): void {
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-15',
            'time_in_at' => CarbonImmutable::parse($timeIn, 'Asia/Manila'),
            'time_out_at' => CarbonImmutable::parse($timeOut, 'Asia/Manila'),
        ]);

        $row = $this->build($employee, '2026-09')['rows'][14];

        $this->assertSame($expectedTotalHours, $row['total_hours']);
    }

    public function test_uses_work_date_for_overnight_attendance(): void
    {
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-23',
            'time_in_at' => CarbonImmutable::parse('2026-09-23 22:00:00', 'Asia/Manila'),
            'time_out_at' => CarbonImmutable::parse('2026-09-24 06:30:00', 'Asia/Manila'),
            'work_arrangement' => WorkArrangement::FieldBased,
        ]);

        $dtr = $this->build($employee, '2026-09');
        $workDateRow = $dtr['rows'][22];
        $nextDateRow = $dtr['rows'][23];

        $this->assertSame('Sep 23, 2026', $workDateRow['date_label']);
        $this->assertSame('10:00 PM', $workDateRow['time_in']);
        $this->assertSame('6:30 AM', $workDateRow['time_out']);
        $this->assertSame('FIELD-BASED', $workDateRow['attendance_rendered']);
        $this->assertSame('', $nextDateRow['time_in']);
    }

    public function test_open_and_missing_attendance_leave_unavailable_values_blank(): void
    {
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->open()->create([
            'work_date' => '2026-09-10',
            'time_in_at' => '2026-09-10 08:25:00',
            'work_arrangement' => WorkArrangement::WorkFromHome,
        ]);

        $dtr = $this->build($employee, '2026-09');
        $openRow = $dtr['rows'][9];
        $missingRow = $dtr['rows'][10];

        $this->assertSame('8:25 AM', $openRow['time_in']);
        $this->assertSame('', $openRow['time_out']);
        $this->assertSame('', $openRow['total_hours']);
        $this->assertSame('WFH', $openRow['attendance_rendered']);
        $this->assertSame('', $openRow['remarks']);
        $this->assertSame('', $missingRow['time_in']);
        $this->assertSame('', $missingRow['attendance_rendered']);
    }

    public function test_duration_shorter_than_the_fixed_break_is_zero(): void
    {
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-15',
            'time_in_at' => '2026-09-15 08:00:00',
            'time_out_at' => '2026-09-15 08:45:00',
        ]);

        $row = $this->build($employee, '2026-09')['rows'][14];

        $this->assertSame('0.00', $row['total_hours']);
    }

    public function test_uses_the_configured_break_minutes(): void
    {
        config()->set('workforce.dtr.break_minutes', 30);
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-15',
            'time_in_at' => '2026-09-15 08:00:00',
            'time_out_at' => '2026-09-15 17:00:00',
        ]);

        $row = $this->build($employee, '2026-09')['rows'][14];

        $this->assertSame('8.50', $row['total_hours']);
    }

    public function test_admin_correction_recalculates_net_hours_from_canonical_values(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $session = AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-15',
            'time_in_at' => '2026-09-15 08:00:00',
            'time_out_at' => '2026-09-15 17:00:00',
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);

        $this->assertSame('8.00', $this->build($employee, '2026-09')['rows'][14]['total_hours']);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            'time_in_at' => '2026-09-15T08:30:00',
            'time_out_at' => '2026-09-15T17:00:00',
            'work_arrangement' => WorkArrangement::OfficeBased->value,
            'reason' => 'Verified against the signed attendance log.',
        ])->assertRedirect(route('admin.attendance.show', $session));

        $row = $this->build($employee, '2026-09')['rows'][14];

        $this->assertSame('8:30 AM', $row['time_in']);
        $this->assertSame('5:00 PM', $row['time_out']);
        $this->assertSame('OFFICE-BASED', $row['attendance_rendered']);
        $this->assertSame('7.50', $row['total_hours']);
    }

    #[DataProvider('workArrangements')]
    public function test_maps_each_work_arrangement_to_its_dtr_label(
        WorkArrangement $arrangement,
        string $expectedLabel,
    ): void {
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-01',
            'work_arrangement' => $arrangement,
        ]);

        $row = $this->build($employee, '2026-09')['rows'][0];

        $this->assertSame($expectedLabel, $row['attendance_rendered']);
        $this->assertSame($arrangement->label(), $row['work_arrangement']);
    }

    public function test_legacy_arrangement_remains_unknown_without_fabricating_a_category(): void
    {
        $employee = Employee::factory()->create();
        AttendanceSession::factory()->for($employee)->legacy()->create([
            'work_date' => '2026-09-01',
        ]);

        $row = $this->build($employee, '2026-09')['rows'][0];

        $this->assertTrue($row['has_legacy_arrangement']);
        $this->assertNull($row['work_arrangement']);
        $this->assertSame('', $row['attendance_rendered']);
    }

    public static function calendarMonths(): array
    {
        return [
            'thirty days' => ['2026-09', 30, '2026-09-30', 'SEPTEMBER 30, 2026'],
            'thirty-one days' => ['2026-01', 31, '2026-01-31', 'JANUARY 31, 2026'],
            'non-leap February' => ['2025-02', 28, '2025-02-28', 'FEBRUARY 28, 2025'],
            'leap February' => ['2024-02', 29, '2024-02-29', 'FEBRUARY 29, 2024'],
        ];
    }

    public static function completedSessionDurations(): array
    {
        return [
            'nine-hour daytime shift' => ['2026-09-15 08:00:00', '2026-09-15 17:00:00', '8.00'],
            'eight-and-a-half-hour daytime shift' => ['2026-09-15 08:00:00', '2026-09-15 16:30:00', '7.50'],
            'nine-hour overnight shift' => ['2026-09-15 22:00:00', '2026-09-16 07:00:00', '8.00'],
        ];
    }

    public static function workArrangements(): array
    {
        return [
            'work from home' => [WorkArrangement::WorkFromHome, 'WFH'],
            'office based' => [WorkArrangement::OfficeBased, 'OFFICE-BASED'],
            'field based' => [WorkArrangement::FieldBased, 'FIELD-BASED'],
        ];
    }

    /** @return array<string, mixed> */
    private function build(Employee $employee, string $month): array
    {
        return app(BuildMonthlyDtr::class)->handle(
            $employee,
            CarbonImmutable::parse("{$month}-01", 'Asia/Manila'),
        );
    }
}
