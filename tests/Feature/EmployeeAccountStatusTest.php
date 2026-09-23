<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EmployeeAccountStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pending_employee_cannot_login(): void
    {
        $user = User::factory()->employee()->pending()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'StrongPassword!123',
        ])->assertSessionHasErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);

        $this->assertGuest();
    }

    public function test_disabled_employee_cannot_login(): void
    {
        $user = User::factory()->employee()->disabled()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);

        $this->assertGuest();
    }

    public function test_admin_can_disable_and_enable_employee_without_removing_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $employeeId = $employee->id;

        $this->actingAs($admin)
            ->patch(route('admin.employees.account.update', $employee), [
                'account_status' => AccountStatus::Disabled->value,
            ])
            ->assertRedirect();

        $this->assertSame(AccountStatus::Disabled, $employee->user->refresh()->account_status);
        $this->assertDatabaseHas('employees', ['id' => $employeeId]);

        $this->actingAs($admin)
            ->patch(route('admin.employees.account.update', $employee), [
                'account_status' => AccountStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertSame(AccountStatus::Active, $employee->user->refresh()->account_status);
        $this->assertDatabaseHas('employees', ['id' => $employeeId]);
    }

    public function test_disabled_authenticated_employee_is_logged_out_on_next_protected_request(): void
    {
        $user = User::factory()->employee()->disabled()->create();

        $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_pending_employee_cannot_be_manually_enabled_by_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->employee()->pending()->create();
        $employee = Employee::factory()->for($user)->create();

        $this->actingAs($admin)
            ->patch(route('admin.employees.account.update', $employee), [
                'account_status' => AccountStatus::Active->value,
            ])
            ->assertSessionHasErrors('account_status');

        $this->assertSame(AccountStatus::Pending, $user->refresh()->account_status);
    }
}
