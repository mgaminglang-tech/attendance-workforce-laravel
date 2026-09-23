<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminAttendanceManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_and_employees_cannot_access_admin_attendance_routes(): void
    {
        $session = AttendanceSession::factory()->create();

        $this->get(route('admin.attendance.index'))->assertRedirect(route('login'));
        $this->get(route('admin.attendance.show', $session))->assertRedirect(route('login'));
        $this->put(route('admin.attendance.correction.update', $session), [
            'time_in_at' => '2026-09-23T08:00:00',
            'time_out_at' => '2026-09-23T17:00:00',
            'reason' => 'Verified correction request.',
        ])->assertRedirect(route('login'));

        $employeeUser = User::factory()->employee()->create();

        $this->actingAs($employeeUser)->get(route('admin.attendance.index'))->assertForbidden();
        $this->actingAs($employeeUser)->get(route('admin.attendance.show', $session))->assertForbidden();
        $this->actingAs($employeeUser)->get(route('admin.attendance.correction.edit', $session))->assertForbidden();
        $this->actingAs($employeeUser)->put(route('admin.attendance.correction.update', $session), [
            'time_in_at' => '2026-09-23T08:00:00',
            'time_out_at' => '2026-09-23T17:00:00',
            'reason' => 'Verified correction request.',
        ])->assertForbidden();
    }

    public function test_active_administrator_can_view_index_and_session_details(): void
    {
        $admin = User::factory()->admin()->create();
        $session = AttendanceSession::factory()->create();

        $this->actingAs($admin)->get(route('admin.attendance.index'))
            ->assertOk()->assertSee($session->employee->user->name);

        $this->actingAs($admin)->get(route('admin.attendance.show', $session))
            ->assertOk()->assertSee($session->employee->employee_number)->assertSee('Correction history');
    }

    public function test_disabled_administrator_cannot_manage_attendance(): void
    {
        $admin = User::factory()->admin()->disabled()->create();

        $this->actingAs($admin)->get(route('admin.attendance.index'))->assertRedirect(route('login'));
    }

    public function test_index_filters_by_employee_department_date_range_and_state(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $matchingEmployee = Employee::factory()->for(
            User::factory()->employee()->state(['name' => 'Filter Match', 'email' => 'filter@example.test']),
        )->for($department)->create(['employee_number' => 'EMP-FILTER']);
        $matching = AttendanceSession::factory()->for($matchingEmployee)->open()->create([
            'work_date' => '2026-09-20',
            'time_in_at' => CarbonImmutable::parse('2026-09-20 08:00:00', config('app.timezone')),
        ]);
        AttendanceSession::factory()->create([
            'work_date' => '2026-09-20',
            'time_in_at' => CarbonImmutable::parse('2026-09-20 09:00:00', config('app.timezone')),
        ]);

        $this->actingAs($admin)->get(route('admin.attendance.index', [
            'search' => 'filter@example.test',
            'department' => $department->id,
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-20',
            'state' => 'open',
        ]))->assertOk()->assertViewHas('attendanceSessions', function ($sessions) use ($matching): bool {
            return $sessions->total() === 1 && $sessions->first()->is($matching);
        });
    }

    public function test_index_validates_filters_and_paginates_in_stable_newest_first_order(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();

        foreach (range(1, 21) as $day) {
            AttendanceSession::factory()->for($employee)->create([
                'work_date' => "2026-08-{$day}",
                'time_in_at' => CarbonImmutable::parse("2026-08-{$day} 08:00:00", config('app.timezone')),
            ]);
        }

        $this->actingAs($admin)->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertViewHas('attendanceSessions', function ($sessions): bool {
                return $sessions->total() === 21
                    && $sessions->perPage() === 20
                    && $sessions->first()->work_date->toDateString() === '2026-08-21';
            });

        $this->actingAs($admin)->get(route('admin.attendance.index', [
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-19',
            'state' => 'invalid',
        ]))->assertSessionHasErrors(['date_to', 'state']);
    }
}
