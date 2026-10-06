<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use App\Services\BuildMonthlyDtr;
use App\Services\MonthlyDtrPdf;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $pdfContent = PdfFacade::loadView('dtr.pdf', ['dtr' => $dtr])
            ->setPaper('a4', 'portrait')->output(['compress' => 0]);
        $netHours = $dtr['rows'][22]['total_hours'];

        $this->assertSame('5.37', $netHours);
        $this->assertStringContainsString($netHours, $previewHtml);
        $this->assertStringContainsString('WORK FROM HOME MONTHLY ATTENDANCE CERTIFICATION', $pdfHtml);
        $this->assertStringContainsString('SEPTEMBER 01, 2026', $pdfHtml);
        $this->assertStringContainsString('SEPTEMBER 30, 2026', $pdfHtml);
        $this->assertStringContainsString('Maria Santos', $pdfHtml);
        $this->assertSame('8:25 AM', $dtr['rows'][22]['time_in']);
        $this->assertSame('1:47 PM', $dtr['rows'][22]['time_out']);
        $this->assertStringContainsString('Work Arrangement', $previewHtml);
        $this->assertStringContainsString('Office-Based', $previewHtml);
        $this->assertStringContainsString('Attendance Rendered', $previewHtml);
        $this->assertStringContainsString('Total Hrs.', $previewHtml);
        $this->assertStringNotContainsString('Time In', $previewHtml);
        $this->assertStringNotContainsString('Time Out', $previewHtml);
        $this->assertStringNotContainsString('8:25 AM', $previewHtml);
        $this->assertStringNotContainsString('1:47 PM', $previewHtml);
        $this->assertStringNotContainsString('8:25 AM', $pdfHtml);
        $this->assertStringNotContainsString('1:47 PM', $pdfHtml);
        $this->assertStringNotContainsString('TIME IN', strip_tags(str_replace('<br>', ' ', $pdfHtml)));
        $this->assertStringNotContainsString('TIME OUT', strip_tags(str_replace('<br>', ' ', $pdfHtml)));
        $this->assertStringContainsString($netHours, $pdfHtml);
        $this->assertStringContainsString('OFFICE-BASED', $pdfHtml);
        $this->assertStringContainsString('Prepared by:', $pdfHtml);
        $this->assertStringNotContainsString('private.employee@example.test', $pdfHtml);
        $this->assertStringNotContainsString('Sensitive correction reason must remain private.', $pdfHtml);
        $this->assertStringNotContainsString('password', mb_strtolower($pdfHtml));
        $this->assertStringNotContainsString('private.employee@example.test', $previewHtml);
        $this->assertStringNotContainsString('Sensitive correction reason must remain private.', $previewHtml);
        $this->assertStringContainsString('(5.37)', $pdfContent);
        $this->assertStringContainsString('(OFFICE-BASED)', $pdfContent);
        $this->assertStringNotContainsString('8:25 AM', $pdfContent);
        $this->assertStringNotContainsString('1:47 PM', $pdfContent);
        $this->assertStringNotContainsString('(TIME)', $pdfContent);
        $this->assertStringNotContainsString('private.employee@example.test', $pdfContent);
        $this->assertStringNotContainsString('Sensitive correction reason must remain private.', $pdfContent);
    }

    public function test_pdf_download_uses_structured_employee_names(): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->for($user)->create([
            'employee_number' => 'EMP 01/2026',
            'first_name' => 'Cris David',
            'last_name' => 'Castro',
        ]);

        $response = $this->actingAs($user)->get(route('employee.dtr.pdf', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'filename=DTR_CASTRO_CRIS-DAVID_2026-09.pdf',
            (string) $response->headers->get('content-disposition'),
        );
    }

    #[DataProvider('employeeNames')]
    public function test_pdf_filename_is_safe_and_never_uses_employee_number(
        ?string $firstName,
        ?string $lastName,
        string $displayName,
        string $expectedFilename,
    ): void {
        $employee = Employee::factory()->make([
            'id' => 901,
            'employee_number' => 'PRIVATE-EMP-901',
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $filename = app(MonthlyDtrPdf::class)->filename([
            'employee' => $employee,
            'employee_name' => $displayName,
            'employee_number' => $employee->employee_number,
            'month' => '2026-09',
        ]);

        $this->assertSame($expectedFilename, $filename);
        $this->assertMatchesRegularExpression('/\ADTR_[A-Z0-9_-]+_2026-09\.pdf\z/', $filename);
        $this->assertStringNotContainsString('PRIVATE-EMP-901', $filename);
        $this->assertStringNotContainsString('901', $filename);
    }

    /** @return array<string, array{?string, ?string, string, string}> */
    public static function employeeNames(): array
    {
        return [
            'structured' => ['Cris David', 'Castro', 'Unrelated Display Name', 'DTR_CASTRO_CRIS-DAVID_2026-09.pdf'],
            'compound components' => ['  Ana   Marie ', ' De la Cruz ', 'Legacy Name', 'DTR_DE-LA-CRUZ_ANA-MARIE_2026-09.pdf'],
            'accented characters' => ['José', 'Muñoz', 'Legacy Name', 'DTR_MUNOZ_JOSE_2026-09.pdf'],
            'unsafe punctuation' => [' ../Cris\\David:*? ', ' ../Castro/// ', 'Legacy Name', 'DTR_CASTRO_CRIS-DAVID_2026-09.pdf'],
            'underscores collapse' => ['Ana__Marie', 'De___la__Cruz', 'Legacy Name', 'DTR_DE-LA-CRUZ_ANA-MARIE_2026-09.pdf'],
            'legacy nulls' => [null, null, '  Ana Marie De la Cruz Jr. ', 'DTR_ANA-MARIE-DE-LA-CRUZ-JR_2026-09.pdf'],
            'partial structured name' => ['Ana', null, 'Existing Display', 'DTR_EXISTING-DISPLAY_2026-09.pdf'],
            'empty structured names' => ['   ', '...', 'Existing Display', 'DTR_EXISTING-DISPLAY_2026-09.pdf'],
            'unsafe legacy display' => [null, null, ' ../Ana\\Marie:<>*? ', 'DTR_ANA-MARIE_2026-09.pdf'],
            'empty legacy display' => [null, null, '../\\:*?<>|', 'DTR_EMPLOYEE_2026-09.pdf'],
        ];
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
        $this->assertSame('', $dtr['rows'][11]['total_hours']);
        $this->assertSame('', $dtr['rows'][11]['time_in']);
        $this->assertSame('', $dtr['rows'][11]['time_out']);
        $this->assertMatchesRegularExpression('/Sep 12, 2026<\/th>\s*<td><\/td>\s*<td>\s*<\/td>\s*<td>ON LEAVE<\/td>/', $previewHtml);
        $this->assertMatchesRegularExpression('/Sep 12, 2026<\/td>\s*<td><\/td>\s*<td>ON LEAVE<\/td>/', $pdfHtml);

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
        $employee = Employee::factory()->for($user)->create();
        $firstDate = CarbonImmutable::parse($month.'-01', 'Asia/Manila');

        foreach (range(1, $firstDate->daysInMonth) as $day) {
            $date = $firstDate->day($day)->toDateString();

            if ($day === 12) {
                EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => $date]);

                continue;
            }

            $session = AttendanceSession::factory()->for($employee)->create([
                'work_date' => $date,
                'time_in_at' => $date.' 08:25:00',
                'time_out_at' => $date.' 13:47:00',
                'work_arrangement' => WorkArrangement::cases()[$day % 3],
            ]);
            /** Store the DATE value as MySQL does, without SQLite retaining the model cast's midnight timestamp. */
            DB::table('attendance_sessions')->where('id', $session->id)->update(['work_date' => $date]);
        }

        $response = $this->actingAs($user)->get(route('employee.dtr.pdf', [
            'month' => $month,
        ]));
        $content = $response->getContent();

        $dtr = app(BuildMonthlyDtr::class)->handle($employee, $firstDate);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame('5.37', $dtr['rows'][$firstDate->daysInMonth - 1]['total_hours']);
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
