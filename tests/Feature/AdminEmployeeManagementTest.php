<?php

namespace Tests\Feature;

use App\Actions\Employees\CreateInvitedEmployee;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Mail\EmployeeInvitationMail;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminEmployeeManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_access_employee_management(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Employees');
    }

    public function test_employee_cannot_access_employee_management(): void
    {
        $employeeUser = User::factory()->employee()->create();

        $this->actingAs($employeeUser)
            ->get(route('admin.employees.index'))
            ->assertForbidden();
    }

    public function test_admin_can_atomically_create_pending_employee_and_hashed_invitation(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.employees.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ADA@example.test',
            'employee_number' => ' emp-1001 ',
            'department_id' => $department->id,
            'job_title' => 'Software Engineer',
            'hired_at' => '2026-09-23',
        ]);

        $user = User::query()->where('email', 'ada@example.test')->firstOrFail();
        $employee = $user->employee;
        $invitation = $user->employeeInvitation;

        $response->assertRedirect(route('admin.employees.show', $employee));
        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertSame(AccountStatus::Pending, $user->account_status);
        $this->assertNull($user->password);
        $this->assertSame('EMP-1001', $employee->employee_number);
        $this->assertTrue($employee->department->is($department));
        $this->assertNotNull($invitation);

        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) use ($user, $invitation): bool {
            return $mail->user->is($user)
                && hash('sha256', $mail->token) === $invitation->token_hash
                && $mail->token !== $invitation->token_hash;
        });
    }

    public function test_duplicate_employee_email_is_rejected(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'existing@example.test']);

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'name' => 'Duplicate Email',
            'email' => 'existing@example.test',
            'employee_number' => 'EMP-2001',
        ])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_duplicate_employee_number_is_rejected(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        Employee::factory()->create(['employee_number' => 'EMP-2002']);

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'name' => 'Duplicate Number',
            'email' => 'unique@example.test',
            'employee_number' => 'EMP-2002',
        ])->assertSessionHasErrors('employee_number');

        Mail::assertNothingSent();
    }

    public function test_employee_creation_rolls_back_all_records_when_profile_creation_fails(): void
    {
        Mail::fake();
        Employee::factory()->create(['employee_number' => 'EMP-ROLLBACK']);

        try {
            $this->app->make(CreateInvitedEmployee::class)->handle(
                name: 'Rollback Employee',
                email: 'rollback@example.test',
                employeeNumber: 'EMP-ROLLBACK',
            );
            $this->fail('Expected duplicate employee number to fail.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
            $this->assertSame(0, EmployeeInvitation::count());
            Mail::assertNothingSent();
        }
    }

    public function test_admin_can_update_allowed_employee_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
            'name' => 'Updated Employee',
            'email' => 'updated@example.test',
            'employee_number' => 'EMP-UPDATED',
            'department_id' => $department->id,
            'job_title' => 'Team Lead',
            'hired_at' => '2026-01-10',
        ]);

        $response->assertRedirect(route('admin.employees.show', $employee));
        $this->assertSame('Updated Employee', $employee->user->refresh()->name);
        $this->assertSame('EMP-UPDATED', $employee->refresh()->employee_number);
        $this->assertTrue($employee->department->is($department));
    }

    public function test_changing_a_pending_employee_email_rotates_the_invitation_to_the_new_address(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $pendingUser = User::factory()->employee()->pending()->create([
            'email' => 'old-address@example.test',
        ]);
        $employee = Employee::factory()->for($pendingUser)->create();
        $invitation = EmployeeInvitation::factory()->for($pendingUser)->create();
        $oldTokenHash = $invitation->token_hash;

        $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
            'name' => $pendingUser->name,
            'email' => 'new-address@example.test',
            'employee_number' => $employee->employee_number,
            'department_id' => null,
            'job_title' => $employee->job_title,
            'hired_at' => $employee->hired_at?->toDateString(),
        ])->assertRedirect(route('admin.employees.show', $employee));

        $invitation->refresh();
        $pendingUser->refresh();

        $this->assertSame('new-address@example.test', $pendingUser->email);
        $this->assertNotSame($oldTokenHash, $invitation->token_hash);
        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) use ($pendingUser, $invitation): bool {
            return $mail->hasTo('new-address@example.test')
                && $mail->user->is($pendingUser)
                && hash('sha256', $mail->token) === $invitation->token_hash;
        });
    }

    public function test_inactive_department_is_not_available_for_new_employee_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $activeDepartment = Department::factory()->create(['name' => 'Active Department']);
        $inactiveDepartment = Department::factory()->inactive()->create(['name' => 'Inactive Department']);

        $this->actingAs($admin)
            ->get(route('admin.employees.create'))
            ->assertSee($activeDepartment->name)
            ->assertDontSee($inactiveDepartment->name);

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'name' => 'Invalid Assignment',
            'email' => 'invalid-assignment@example.test',
            'employee_number' => 'EMP-3001',
            'department_id' => $inactiveDepartment->id,
        ])->assertSessionHasErrors('department_id');
    }

    public function test_employee_index_searches_filters_and_paginates_server_side(): void
    {
        $admin = User::factory()->admin()->create();
        $engineering = Department::factory()->create();
        $operations = Department::factory()->create();
        $ada = User::factory()->employee()->create(['name' => 'Ada Searchable']);
        $bob = User::factory()->employee()->disabled()->create(['name' => 'Bob Hidden']);
        Employee::factory()->for($ada)->for($engineering)->create(['employee_number' => 'EMP-ADA']);
        Employee::factory()->for($bob)->for($operations)->create(['employee_number' => 'EMP-BOB']);
        Employee::factory()->count(14)->create();

        $this->actingAs($admin)
            ->get(route('admin.employees.index', [
                'search' => 'Ada',
                'department' => $engineering->id,
                'account_status' => AccountStatus::Active->value,
            ]))
            ->assertSee('Ada Searchable')
            ->assertDontSee('Bob Hidden');

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertViewHas('employees', fn ($employees): bool => $employees->total() === 16 && $employees->perPage() === 15);
    }
}
