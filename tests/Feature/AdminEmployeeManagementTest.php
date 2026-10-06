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
use PHPUnit\Framework\Attributes\DataProvider;
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
            'first_name' => "  Ada   Marie\t",
            'last_name' => '  De la   Cruz ',
            'name' => 'Ignored client display name',
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
        $this->assertSame('Ada Marie', $employee->first_name);
        $this->assertSame('De la Cruz', $employee->last_name);
        $this->assertSame('Ada Marie De la Cruz', $user->name);
        $this->assertSame('Software Engineer', $employee->job_title);
        $this->assertSame('2026-09-23', $employee->hired_at->toDateString());
        $this->assertNull($admin->employee);
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
            'first_name' => 'Duplicate',
            'last_name' => 'Email',
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
            'first_name' => 'Duplicate',
            'last_name' => 'Number',
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
                firstName: 'Rollback',
                lastName: 'Employee',
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
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
            'first_name' => '  Updated   First ',
            'last_name' => ' Del   Rosario ',
            'email' => 'updated@example.test',
            'employee_number' => 'EMP-UPDATED',
            'department_id' => $department->id,
            'job_title' => 'Team Lead',
            'hired_at' => '2026-01-10',
        ]);

        $response->assertRedirect(route('admin.employees.show', $employee));
        $this->assertSame('Updated First Del Rosario', $employee->user->refresh()->name);
        $this->assertSame('EMP-UPDATED', $employee->refresh()->employee_number);
        $this->assertSame('Updated First', $employee->first_name);
        $this->assertSame('Del Rosario', $employee->last_name);
        $this->assertTrue($employee->department->is($department));
        $this->assertSame('Team Lead', $employee->job_title);
        $this->assertSame('2026-01-10', $employee->hired_at->toDateString());
        Mail::assertNothingSent();
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
            'first_name' => 'Cris David',
            'last_name' => 'Castro',
            'email' => 'new-address@example.test',
            'employee_number' => $employee->employee_number,
            'department_id' => null,
            'job_title' => $employee->job_title,
            'hired_at' => $employee->hired_at?->toDateString(),
        ])->assertRedirect(route('admin.employees.show', $employee));

        $invitation->refresh();
        $pendingUser->refresh();

        $this->assertSame('new-address@example.test', $pendingUser->email);
        $this->assertSame('Cris David Castro', $pendingUser->name);
        $this->assertSame('Cris David', $employee->refresh()->first_name);
        $this->assertSame('Castro', $employee->last_name);
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
            'first_name' => 'Invalid',
            'last_name' => 'Assignment',
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

    public function test_admin_forms_expose_structured_names_without_guessing_legacy_names(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()
            ->for(User::factory()->employee()->state(['name' => 'Ana Marie De la Cruz Jr.']))
            ->create();

        $this->actingAs($admin)->get(route('admin.employees.create'))
            ->assertSee('First Name')->assertSee('Last Name')
            ->assertSee('name="first_name"', false)->assertSee('name="last_name"', false)
            ->assertDontSee('name="name"', false);
        $legacyForm = $this->get(route('admin.employees.edit', $employee));

        $this->assertMatchesRegularExpression('/id="first_name"[^>]*value=""/', $legacyForm->getContent());
        $this->assertMatchesRegularExpression('/id="last_name"[^>]*value=""/', $legacyForm->getContent());
        $this->assertNull($employee->refresh()->first_name);
        $this->assertNull($employee->last_name);
        $this->assertSame('Ana Marie De la Cruz Jr.', $employee->user->name);

        $employee->update(['first_name' => 'Ana Marie', 'last_name' => 'De la Cruz Jr.']);

        $this->get(route('admin.employees.edit', $employee))
            ->assertSee('value="Ana Marie"', false)
            ->assertSee('value="De la Cruz Jr."', false);
    }

    #[DataProvider('invalidStructuredNames')]
    public function test_admin_create_and_update_reject_invalid_names_without_changes(
        bool $updating,
        array $nameFields,
        array $expectedErrors,
    ): void {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $employee = $updating ? Employee::factory()->create(['first_name' => 'Existing', 'last_name' => 'Employee']) : null;
        $originalName = $employee?->user->name;
        $payload = array_merge([
            'email' => 'invalid-names@example.test',
            'employee_number' => 'EMP-INVALID-NAMES',
            'name' => 'Legacy full name cannot replace required fields',
        ], $nameFields);

        $response = $updating
            ? $this->actingAs($admin)->put(route('admin.employees.update', $employee), $payload)
            : $this->actingAs($admin)->post(route('admin.employees.store'), $payload);

        $response->assertSessionHasErrors($expectedErrors);
        $this->assertDatabaseMissing('users', ['email' => 'invalid-names@example.test']);
        $this->assertDatabaseMissing('employees', ['employee_number' => 'EMP-INVALID-NAMES']);
        $this->assertSame($updating ? 1 : 0, Employee::count());

        if ($employee !== null) {
            $this->assertSame('Existing', $employee->refresh()->first_name);
            $this->assertSame('Employee', $employee->last_name);
            $this->assertSame($originalName, $employee->user->name);
        }

        Mail::assertNothingSent();
    }

    /** @return array<string, array{bool, array<string, mixed>, array<string, string>}> */
    public static function invalidStructuredNames(): array
    {
        $cases = [
            'missing both' => [[], [
                'first_name' => 'The first name field is required.',
                'last_name' => 'The last name field is required.',
            ]],
            'missing first' => [['last_name' => 'Castro'], ['first_name' => 'The first name field is required.']],
            'missing last' => [['first_name' => 'Cris David'], ['last_name' => 'The last name field is required.']],
            'whitespace only' => [['first_name' => " \t ", 'last_name' => " \n "], [
                'first_name' => 'The first name field is required.',
                'last_name' => 'The last name field is required.',
            ]],
            'non-string first' => [['first_name' => ['Cris'], 'last_name' => 'Castro'], ['first_name' => 'The first name field must be a string.']],
            'non-string last' => [['first_name' => 'Cris', 'last_name' => ['Castro']], ['last_name' => 'The last name field must be a string.']],
            'long first' => [['first_name' => str_repeat('a', 256), 'last_name' => 'Castro'], ['first_name' => 'The first name field must not be greater than 255 characters.']],
            'long last' => [['first_name' => 'Cris', 'last_name' => str_repeat('a', 256)], ['last_name' => 'The last name field must not be greater than 255 characters.']],
            'combined name exceeds account column' => [['first_name' => str_repeat('a', 128), 'last_name' => str_repeat('b', 127)], [
                'last_name' => 'The combined first and last name must not exceed 255 characters.',
            ]],
        ];
        $requests = [];

        foreach (['create' => false, 'update' => true] as $operation => $updating) {
            foreach ($cases as $label => [$fields, $errors]) {
                $requests[$operation.' '.$label] = [$updating, $fields, $errors];
            }
        }

        return $requests;
    }

    public function test_pending_name_update_with_unchanged_email_keeps_the_existing_invitation(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $pendingUser = User::factory()->employee()->pending()->create();
        $employee = Employee::factory()->for($pendingUser)->create();
        $invitation = EmployeeInvitation::factory()->for($pendingUser)->create();
        $originalHash = $invitation->token_hash;

        $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
            'first_name' => 'Ana Marie',
            'last_name' => 'De la Cruz',
            'email' => $pendingUser->email,
            'employee_number' => $employee->employee_number,
        ])->assertRedirect(route('admin.employees.show', $employee));

        $this->assertSame('Ana Marie De la Cruz', $pendingUser->refresh()->name);
        $this->assertSame('Ana Marie', $employee->refresh()->first_name);
        $this->assertSame('De la Cruz', $employee->last_name);
        $this->assertSame($originalHash, $invitation->refresh()->token_hash);
        Mail::assertNothingSent();
    }

    public function test_employee_cannot_create_or_update_employee_profiles(): void
    {
        Mail::fake();
        $user = User::factory()->employee()->create();
        $employee = Employee::factory()->create(['first_name' => 'Original', 'last_name' => 'Employee']);
        $payload = [
            'first_name' => 'Unauthorized', 'last_name' => 'Change',
            'email' => 'unauthorized@example.test', 'employee_number' => 'EMP-UNAUTHORIZED',
        ];

        $this->actingAs($user)->post(route('admin.employees.store'), $payload)->assertForbidden();
        $this->put(route('admin.employees.update', $employee), $payload)->assertForbidden();

        $this->assertSame('Original', $employee->refresh()->first_name);
        $this->assertSame('Employee', $employee->last_name);
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
        $this->assertSame(1, Employee::count());
        Mail::assertNothingSent();
    }
}
