<?php

namespace Tests\Feature;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_global_admin_can_filter_all_departments_and_employees(): void
    {
        $admin = User::factory()->admin()->create();
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        $financeEmployee = $this->employeeIn($finance, 'Finance Employee', 'FIN-001');
        $itEmployee = $this->employeeIn($it, 'IT Employee', 'IT-001');
        AttendanceSession::factory()->for($financeEmployee)->create(['work_date' => '2026-09-10']);
        AttendanceSession::factory()->for($itEmployee)->create(['work_date' => '2026-09-10']);

        $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]))->assertOk()
            ->assertSee('Finance Employee')
            ->assertSee('IT Employee');

        $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'department' => $finance->id,
            'employee' => $financeEmployee->id,
        ]))->assertOk()
            ->assertViewHas('sessions', fn ($sessions): bool => $sessions->total() === 1)
            ->assertSee('Finance Employee');
    }

    public function test_bulk_department_dtr_download_is_presented_once_in_an_accessible_modal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Finance']);

        $response = $this->actingAs($admin)->get(route('admin.reports.attendance.index'));

        $response->assertOk()
            ->assertSee('data-bs-target="#bulk-dtr-modal"', false)
            ->assertSee('aria-labelledby="bulk-dtr-heading"', false)
            ->assertSee('data-bs-dismiss="modal"', false)
            ->assertSee('action="'.route('admin.reports.dtr.bulk').'"', false)
            ->assertSee('name="department"', false)
            ->assertSee('type="month"', false)
            ->assertSee('value="2026-09"', false)
            ->assertSee('September 2026')
            ->assertSee($department->name);

        $this->assertSame(1, substr_count($response->getContent(), route('admin.reports.dtr.bulk')));
    }

    public function test_report_filters_and_summaries_use_net_hours_and_open_state(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $completedEmployee = $this->employeeIn($department, 'Completed Employee', 'EMP-001');
        $openEmployee = $this->employeeIn($department, 'Open Employee', 'EMP-002');
        AttendanceSession::factory()->for($completedEmployee)->create([
            'work_date' => '2026-09-12',
            'time_in_at' => $this->manila('2026-09-12 08:00:00'),
            'time_out_at' => $this->manila('2026-09-12 13:22:00'),
            'work_arrangement' => WorkArrangement::WorkFromHome,
        ]);
        AttendanceSession::factory()->for($openEmployee)->open()->create([
            'work_date' => '2026-09-13',
            'time_in_at' => $this->manila('2026-09-13 09:00:00'),
            'work_arrangement' => WorkArrangement::OfficeBased,
        ]);
        AttendanceSession::factory()->for($completedEmployee)->create([
            'work_date' => '2026-08-31',
            'work_arrangement' => WorkArrangement::WorkFromHome,
        ]);

        $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'work_arrangement' => WorkArrangement::WorkFromHome->value,
            'state' => 'completed',
        ]))->assertOk()
            ->assertViewHas('sessions', function ($sessions): bool {
                $row = $sessions->first();

                return $sessions->total() === 1
                    && $row['net_hours'] === '5.37'
                    && $row['work_arrangement'] === 'Work From Home'
                    && $row['status'] === 'Completed';
            })
            ->assertViewHas('summary', fn (array $summary): bool => $summary === [
                'records' => 1,
                'unique_employees' => 1,
                'completed' => 1,
                'open' => 0,
                'on_leave' => 0,
                'total_net_hours' => '5.37',
            ]);

        $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'state' => 'open',
        ]))->assertOk()
            ->assertViewHas('sessions', function ($sessions): bool {
                $row = $sessions->first();

                return $sessions->total() === 1
                    && $row['net_hours'] === ''
                    && $row['status'] === 'Open / Working';
            });
    }

    public function test_report_uses_corrected_canonical_attendance_values(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $session = AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-15',
            'time_in_at' => $this->manila('2026-09-15 08:00:00'),
            'time_out_at' => $this->manila('2026-09-15 17:00:00'),
            'work_arrangement' => WorkArrangement::FieldBased,
        ]);

        $this->actingAs($admin)->put(route('admin.attendance.correction.update', $session), [
            'time_in_at' => '2026-09-15T08:30:00',
            'time_out_at' => '2026-09-15T17:00:00',
            'work_arrangement' => WorkArrangement::FieldBased->value,
            'reason' => 'Verified against the signed attendance log.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('2026-09-15', $session->refresh()->work_date->toDateString());

        $response = $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
        ]))->assertOk();

        $response->assertViewHas('sessions', function ($sessions): bool {
            $row = $sessions->first();

            return $sessions->total() === 1
                && $row['time_in'] === '8:30 AM'
                && $row['time_out'] === '5:00 PM'
                && $row['net_hours'] === '7.50'
                && $row['work_arrangement'] === 'Field-Based';
        });
    }

    public function test_report_includes_and_filters_leave_without_fabricating_attendance_values(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $leaveEmployee = $this->employeeIn($department, 'Leave Employee', 'EMP-LEAVE');
        $attendanceEmployee = $this->employeeIn($department, 'Attendance Employee', 'EMP-WORK');
        EmployeeLeaveDay::factory()->for($leaveEmployee)->create(['leave_date' => '2026-09-12']);
        AttendanceSession::factory()->for($attendanceEmployee)->create(['work_date' => '2026-09-12']);

        $response = $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'state' => 'on_leave',
        ]))->assertOk()
            ->assertSee('Leave Employee');

        $response->assertViewHas('sessions', function ($sessions): bool {
            $row = $sessions->first();

            return $sessions->total() === 1
                && $row['status'] === 'On Leave'
                && $row['time_in'] === ''
                && $row['time_out'] === ''
                && $row['net_hours'] === ''
                && $row['work_arrangement'] === null;
        })->assertViewHas('summary', fn (array $summary): bool => $summary === [
            'records' => 1,
            'unique_employees' => 1,
            'completed' => 0,
            'open' => 0,
            'on_leave' => 1,
            'total_net_hours' => '0.00',
        ]);

        $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'work_arrangement' => WorkArrangement::OfficeBased->value,
        ]))->assertOk()->assertViewHas(
            'sessions',
            fn ($sessions): bool => ! $sessions->contains(fn (array $row): bool => $row['status'] === 'On Leave'),
        );
    }

    public function test_report_rejects_invalid_ranges_and_preserves_filters_while_paginating(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();

        foreach (range(1, 26) as $day) {
            AttendanceSession::factory()->for($employee)->create([
                'work_date' => "2026-09-{$day}",
                'time_in_at' => $this->manila("2026-09-{$day} 08:00:00"),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'state' => 'completed',
        ]))->assertOk();

        $response->assertViewHas('sessions', function ($sessions): bool {
            return $sessions->total() === 26
                && $sessions->perPage() === 25
                && str_contains($sessions->url(2), 'state=completed')
                && str_contains($sessions->url(2), 'date_from=2026-09-01');
        });

        $this->actingAs($admin)->get(route('admin.reports.attendance.index', [
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-19',
        ]))->assertSessionHasErrors([
            'date_to' => 'Date To must be on or after Date From.',
        ]);
    }

    public function test_hr_report_is_fixed_to_the_assigned_department_and_rejects_tampering(): void
    {
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        $representative = User::factory()->employee()->has(Employee::factory())->create();
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();
        $financeEmployee = $this->employeeIn($finance, 'Finance Employee', 'FIN-001');
        $itEmployee = $this->employeeIn($it, 'IT Employee', 'IT-001');
        AttendanceSession::factory()->for($financeEmployee)->create(['work_date' => '2026-09-10']);
        AttendanceSession::factory()->for($itEmployee)->create(['work_date' => '2026-09-10']);
        EmployeeLeaveDay::factory()->for($financeEmployee)->create(['leave_date' => '2026-09-11']);
        EmployeeLeaveDay::factory()->for($itEmployee)->create(['leave_date' => '2026-09-11']);

        $this->actingAs($representative)->get(route('hr.reports.attendance.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]))->assertOk()
            ->assertSee('Department:')
            ->assertSee('type="month"', false)
            ->assertSee('data-bs-target="#bulk-dtr-modal"', false)
            ->assertSee('Fixed to your assigned department.')
            ->assertSee('Finance Employee')
            ->assertDontSee('IT Employee')
            ->assertSee('On Leave')
            ->assertViewHas('employees', fn ($employees): bool => $employees->modelKeys() === [$financeEmployee->id]);

        $this->actingAs($representative)->get(route('hr.reports.attendance.index', [
            'department' => $it->id,
        ]))->assertForbidden();
    }

    public function test_employee_cannot_access_admin_or_hr_reports(): void
    {
        $employee = User::factory()->employee()->has(Employee::factory())->create();

        $this->actingAs($employee)->get(route('admin.reports.attendance.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('hr.reports.attendance.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('hr.reports.dtr.bulk', ['month' => '2026-09']))->assertForbidden();
    }

    public function test_inactive_hr_representative_cannot_access_assigned_department_reports(): void
    {
        $department = Department::factory()->create();
        $representative = User::factory()->employee()->create();
        Employee::factory()->for($representative)->for($department)->inactive()->create();
        DepartmentHrAssignment::factory()->for($department)->for($representative)->create();

        $this->assertFalse(Gate::forUser($representative)->allows('viewReports', $department));
        $this->actingAs($representative)->get(route('hr.reports.attendance.index'))->assertForbidden();
        $this->actingAs($representative)->get(route('hr.reports.dtr.bulk', [
            'month' => '2026-09',
        ]))->assertForbidden();
    }

    private function employeeIn(Department $department, string $name, string $employeeNumber): Employee
    {
        return Employee::factory()
            ->for(User::factory()->employee()->state(['name' => $name]))
            ->for($department)
            ->create(['employee_number' => $employeeNumber]);
    }

    private function manila(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'));
    }
}
