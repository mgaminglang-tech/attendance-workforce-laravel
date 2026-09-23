<?php

namespace Tests\Feature;

use App\Actions\Attendance\TimeInEmployee;
use App\Enums\AccountStatus;
use App\Exceptions\AttendanceActionException;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttendanceAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('attendanceRoutes')]
    public function test_guest_cannot_access_attendance_routes(string $method, string $routeName): void
    {
        $this->call($method, route($routeName))
            ->assertRedirect(route('login'));
    }

    #[DataProvider('attendanceRoutes')]
    public function test_admin_cannot_access_employee_attendance_routes(string $method, string $routeName): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->call($method, route($routeName))
            ->assertForbidden();

        $this->assertSame(0, AttendanceSession::count());
    }

    #[DataProvider('nonActiveAccountStatuses')]
    public function test_non_active_account_cannot_time_in(AccountStatus $accountStatus): void
    {
        $user = User::factory()->employee()->create([
            'account_status' => $accountStatus,
            'password' => $accountStatus === AccountStatus::Pending ? null : 'password',
        ]);
        Employee::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('employee.attendance.time-in'))
            ->assertRedirect(route('login'));

        $this->assertSame(0, AttendanceSession::count());
    }

    #[DataProvider('nonActiveAccountStatuses')]
    public function test_domain_action_rejects_non_active_account(AccountStatus $accountStatus): void
    {
        $user = User::factory()->employee()->create([
            'account_status' => $accountStatus,
            'password' => $accountStatus === AccountStatus::Pending ? null : 'password',
        ]);
        Employee::factory()->for($user)->create();

        try {
            $this->app->make(TimeInEmployee::class)->handle($user);
            $this->fail('Expected the account status to reject Time In.');
        } catch (AttendanceActionException $exception) {
            $this->assertSame('Your account is not permitted to record attendance.', $exception->getMessage());
            $this->assertSame(0, AttendanceSession::count());
        }
    }

    public function test_inactive_employee_cannot_time_in(): void
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->inactive()->for($user)->create();

        $this->actingAs($user)
            ->post(route('employee.attendance.time-in'))
            ->assertRedirect(route('employee.attendance.index'))
            ->assertSessionHasErrors([
                'attendance' => 'Your employment status does not permit attendance actions.',
            ]);

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_inactive_employee_cannot_time_out_an_existing_open_session(): void
    {
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->inactive()->for($user)->create();
        $attendanceSession = AttendanceSession::factory()->for($employee)->open()->create();

        $this->actingAs($user)
            ->post(route('employee.attendance.time-out'))
            ->assertSessionHasErrors([
                'attendance' => 'Your employment status does not permit attendance actions.',
            ]);

        $this->assertNull($attendanceSession->refresh()->time_out_at);
    }

    public function test_employee_without_profile_cannot_time_in(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)
            ->post(route('employee.attendance.time-in'))
            ->assertSessionHasErrors([
                'attendance' => 'No employee profile is available for this account.',
            ]);

        $this->assertSame(0, AttendanceSession::count());
    }

    /** @return array<string, array{string, string}> */
    public static function attendanceRoutes(): array
    {
        return [
            'dashboard' => ['GET', 'employee.attendance.index'],
            'history' => ['GET', 'employee.attendance.history'],
            'time in' => ['POST', 'employee.attendance.time-in'],
            'time out' => ['POST', 'employee.attendance.time-out'],
        ];
    }

    /** @return array<string, array{AccountStatus}> */
    public static function nonActiveAccountStatuses(): array
    {
        return [
            'pending' => [AccountStatus::Pending],
            'disabled' => [AccountStatus::Disabled],
        ];
    }
}
