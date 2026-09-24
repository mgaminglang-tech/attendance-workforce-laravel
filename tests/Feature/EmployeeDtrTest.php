<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeDtrTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_employee_can_open_dtr_page_and_preview_only_own_attendance(): void
    {
        [$user, $employee] = $this->activeEmployee('Own Employee');
        [$otherUser, $otherEmployee] = $this->activeEmployee('Other Employee');
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-05',
            'time_in_at' => '2026-09-05 08:25:00',
            'time_out_at' => '2026-09-05 17:10:00',
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);
        AttendanceSession::factory()->for($otherEmployee)->create([
            'work_date' => '2026-09-05',
            'time_in_at' => '2026-09-05 06:15:00',
            'time_out_at' => '2026-09-05 15:15:00',
        ]);

        $this->actingAs($user)->get(route('employee.dtr.index'))
            ->assertOk()
            ->assertSee('Monthly Daily Time Record');

        $this->actingAs($user)->get(route('employee.dtr.preview', [
            'month' => '2026-09',
            'employee_id' => $otherEmployee->id,
        ]))->assertOk()
            ->assertSee('September 2026')
            ->assertSee('type="month"', false)
            ->assertSee('value="2026-09"', false)
            ->assertSee('Own Employee')
            ->assertSee('8:25 AM')
            ->assertSee('OFFICE-BASED')
            ->assertDontSee('Other Employee')
            ->assertDontSee('6:15 AM')
            ->assertViewHas('dtr', fn (array $dtr): bool => $dtr['employee']->is($employee));

        $this->assertNotSame($user->id, $otherUser->id);
    }

    public function test_employee_can_download_own_pdf(): void
    {
        [$user, $employee] = $this->activeEmployee('PDF Employee');
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-01',
            'work_arrangement' => WorkArrangement::WorkFromHome,
        ]);

        $response = $this->actingAs($user)->get(route('employee.dtr.pdf', [
            'month' => '2026-09',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            "filename=DTR_{$employee->employee_number}_2026-09.pdf",
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    #[DataProvider('invalidMonths')]
    public function test_employee_month_selection_requires_strict_year_month(
        string $month,
        string $expectedMessage,
    ): void {
        [$user] = $this->activeEmployee();

        $this->actingAs($user)
            ->from(route('employee.dtr.index'))
            ->get(route('employee.dtr.preview', ['month' => $month]))
            ->assertRedirect(route('employee.dtr.index'))
            ->assertSessionHasErrors(['month' => $expectedMessage]);
    }

    public function test_guest_admin_and_disabled_employee_cannot_use_employee_dtr_endpoints(): void
    {
        $admin = User::factory()->admin()->create();
        [$disabledUser] = $this->activeEmployee();
        $disabledUser->forceFill(['account_status' => 'disabled'])->save();

        $this->get(route('employee.dtr.index'))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('employee.dtr.index'))->assertForbidden();
        $this->actingAs($disabledUser)->get(route('employee.dtr.index'))->assertRedirect(route('login'));
    }

    public static function invalidMonths(): array
    {
        return [
            'missing' => ['', 'Select a month for the DTR.'],
            'single-digit month' => ['2026-9', 'The DTR month must use the YYYY-MM format.'],
            'month thirteen' => ['2026-13', 'The DTR month must use the YYYY-MM format.'],
            'extra date component' => ['2026-09-01', 'The DTR month must use the YYYY-MM format.'],
        ];
    }

    /** @return array{User, Employee} */
    private function activeEmployee(string $name = 'Employee User'): array
    {
        $user = User::factory()->employee()->create(['name' => $name]);
        $employee = Employee::factory()->for($user)->create();

        return [$user, $employee];
    }
}
