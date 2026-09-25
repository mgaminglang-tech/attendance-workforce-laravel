<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use App\Services\BuildMonthlyDtr;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DtrPdfTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_preview_and_pdf_template_use_the_same_net_hours_builder_value_without_sensitive_data(): void
    {
        $user = User::factory()->employee()->create([
            'name' => 'Maria Santos',
            'email' => 'private.employee@example.test',
        ]);
        $employee = Employee::factory()->for($user)->create(['employee_number' => 'EMP-0123']);
        $session = AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-23',
            'time_in_at' => '2026-09-23 08:25:00',
            'time_out_at' => '2026-09-23 13:47:00',
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);
        AttendanceAdjustment::factory()->for($session)->create([
            'reason' => 'Sensitive correction reason must remain private.',
        ]);
        $dtr = app(BuildMonthlyDtr::class)->handle(
            $employee,
            CarbonImmutable::parse('2026-09-01', 'Asia/Manila'),
        );

        $previewHtml = view('dtr._preview', [
            'dtr' => $dtr,
            'pdfUrl' => '/dtr.pdf',
        ])->render();
        $pdfHtml = view('dtr.pdf', ['dtr' => $dtr])->render();
        $netHours = $dtr['rows'][22]['total_hours'];

        $this->assertSame('5.37', $netHours);
        $this->assertStringContainsString($netHours, $previewHtml);
        $this->assertStringContainsString('WORK FROM HOME MONTHLY ATTENDANCE CERTIFICATION', $pdfHtml);
        $this->assertStringContainsString('SEPTEMBER 01, 2026', $pdfHtml);
        $this->assertStringContainsString('SEPTEMBER 30, 2026', $pdfHtml);
        $this->assertStringContainsString('Maria Santos', $pdfHtml);
        $this->assertStringContainsString('8:25 AM', $pdfHtml);
        $this->assertStringContainsString('1:47 PM', $pdfHtml);
        $this->assertStringContainsString($netHours, $pdfHtml);
        $this->assertStringContainsString('OFFICE-BASED', $pdfHtml);
        $this->assertStringContainsString('Prepared by:', $pdfHtml);
        $this->assertStringNotContainsString('private.employee@example.test', $pdfHtml);
        $this->assertStringNotContainsString('Sensitive correction reason must remain private.', $pdfHtml);
        $this->assertStringNotContainsString('password', mb_strtolower($pdfHtml));
    }

    public function test_pdf_filename_sanitizes_employee_number(): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->create(['employee_number' => 'EMP 01/2026']);

        $response = $this->actingAs($user)->get(route('employee.dtr.pdf', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'filename=DTR_EMP_01_2026_2026-09.pdf',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_leave_appears_in_employee_preview_and_pdf_without_fabricated_attendance_values(): void
    {
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->for($user)->create();
        EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-12']);
        $dtr = app(BuildMonthlyDtr::class)->handle(
            $employee,
            CarbonImmutable::parse('2026-09-01', 'Asia/Manila'),
        );

        $previewHtml = view('dtr._preview', ['dtr' => $dtr, 'pdfUrl' => '/dtr.pdf'])->render();
        $pdfHtml = view('dtr.pdf', ['dtr' => $dtr])->render();

        $this->assertStringContainsString('ON LEAVE', $previewHtml);
        $this->assertStringContainsString('ON LEAVE', $pdfHtml);
        $this->assertStringNotContainsString('<span class="badge arrangement-badge"></span>', $previewHtml);

        $this->actingAs($user)->get(route('employee.dtr.preview', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('ON LEAVE');

        $this->actingAs($user)->get(route('employee.dtr.pdf', ['month' => '2026-09']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[DataProvider('calendarMonths')]
    public function test_pdf_is_single_page_for_every_supported_month_length(string $month): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('employee.dtr.pdf', [
            'month' => $month,
        ]));
        $content = $response->getContent();

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $content));
    }

    public static function calendarMonths(): array
    {
        return [
            'twenty-eight days' => ['2025-02'],
            'twenty-nine days' => ['2024-02'],
            'thirty days' => ['2026-09'],
            'thirty-one days' => ['2026-01'],
        ];
    }
}
