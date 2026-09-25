<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GENERIC_STATUS = 'If an eligible account exists for that email, a password reset link has been sent.';

    public function test_guests_can_open_password_recovery_pages_from_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee(route('password.request'), false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee('Email address')
            ->assertSee('Back to sign in');

        $this->get(route('password.reset', [
            'token' => 'invalid-token',
            'email' => 'employee@example.com',
        ]))->assertOk()
            ->assertSee('New password')
            ->assertSee('Confirm new password')
            ->assertSee('value="invalid-token"', false);
    }

    public function test_authenticated_users_are_redirected_away_from_password_recovery_routes(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)
            ->get(route('password.request'))
            ->assertRedirect(route('employee.dashboard'));

        $this->actingAs($user)
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('employee.dashboard'));
    }

    public function test_active_employee_hr_representative_and_admin_can_request_reset_notifications(): void
    {
        Notification::fake();
        $department = Department::factory()->create();
        $employeeUser = User::factory()->employee()->create();
        Employee::factory()->for($employeeUser)->create();
        $hrUser = User::factory()->employee()->create();
        Employee::factory()->for($hrUser)->for($department)->create();
        DepartmentHrAssignment::factory()->for($department)->for($hrUser)->create();
        $admin = User::factory()->admin()->create();

        foreach ([$employeeUser, $hrUser, $admin] as $user) {
            $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => '  '.$user->email.'  '])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('status', self::GENERIC_STATUS);

            Notification::assertSentTo($user, ResetPasswordNotification::class);
            $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        }
    }

    public function test_reset_notification_contains_a_framework_reset_url_and_stores_only_a_hash(): void
    {
        Notification::fake();
        $user = User::factory()->employee()->create();
        $token = null;

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user, &$token): bool {
                $token = $notification->token;
                $resetUrl = $notification->toMail($user)->actionUrl;

                $this->assertStringContainsString('/reset-password/', $resetUrl);
                $this->assertStringContainsString('email='.urlencode($user->email), $resetUrl);

                return true;
            },
        );

        $storedToken = (string) $this->app['db']->table('password_reset_tokens')
            ->where('email', $user->email)
            ->value('token');

        $this->assertNotSame($token, $storedToken);
        $this->assertTrue(Hash::check($token, $storedToken));
    }

    public function test_unknown_pending_and_disabled_accounts_receive_the_same_generic_response_without_notification(): void
    {
        Notification::fake();
        $pending = User::factory()->employee()->pending()->create();
        $disabled = User::factory()->employee()->disabled()->create();

        foreach (['unknown@example.com', $pending->email, $disabled->email] as $email) {
            $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => $email])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('status', self::GENERIC_STATUS)
                ->assertSessionHasNoErrors();
        }

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_valid_token_resets_password_once_without_changing_account_or_employment_data(): void
    {
        Event::fake([PasswordReset::class]);
        $department = Department::factory()->create();
        $user = User::factory()->employee()->create(['remember_token' => 'remember-before-reset']);
        $employee = Employee::factory()
            ->for($user)
            ->for($department)
            ->inactive()
            ->create(['employee_number' => 'EMP-RESET']);
        DepartmentHrAssignment::factory()->for($department)->for($user)->create();
        $token = Password::createToken($user);
        $newPassword = 'NewStrongPassword!123';

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your password has been reset. You can now sign in.');

        $user->refresh();
        $employee->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertNotSame('remember-before-reset', $user->remember_token);
        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertSame(AccountStatus::Active, $user->account_status);
        $this->assertSame(EmploymentStatus::Inactive, $employee->employment_status);
        $this->assertTrue($employee->department->is($department));
        $this->assertSame('EMP-RESET', $employee->employee_number);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));

        $this->from(route('password.request'))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'AnotherStrong!456',
                'password_confirmation' => 'AnotherStrong!456',
            ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => 'This password reset link is invalid or has expired.',
            ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $newPassword,
        ])->assertRedirect(route('employee.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_and_disabled_accounts_cannot_use_preexisting_reset_tokens(): void
    {
        $pending = User::factory()->employee()->pending()->create();
        $disabled = User::factory()->employee()->disabled()->create();
        $disabledPassword = $disabled->password;
        $newPassword = 'NewStrongPassword!123';

        foreach ([$pending, $disabled] as $user) {
            $token = Password::createToken($user);

            $this->from(route('password.request'))
                ->post(route('password.update'), [
                    'token' => $token,
                    'email' => $user->email,
                    'password' => $newPassword,
                    'password_confirmation' => $newPassword,
                ])->assertRedirect(route('password.request'))
                ->assertSessionHasErrors([
                    'email' => 'This password reset link is invalid or has expired.',
                ]);
        }

        $this->assertNull($pending->refresh()->password);
        $this->assertSame(AccountStatus::Pending, $pending->account_status);
        $this->assertSame($disabledPassword, $disabled->refresh()->password);
        $this->assertSame(AccountStatus::Disabled, $disabled->account_status);

        $this->post(route('login.store'), [
            'email' => $disabled->email,
            'password' => $newPassword,
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_and_expired_reset_tokens_are_rejected_with_the_same_safe_error(): void
    {
        $user = User::factory()->employee()->create();
        $password = 'NewStrongPassword!123';

        $this->from(route('password.request'))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => 'This password reset link is invalid or has expired.',
            ]);

        $token = Password::createToken($user);
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->from(route('password.request'))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => 'This password reset link is invalid or has expired.',
            ]);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    #[DataProvider('invalidPasswords')]
    public function test_password_reset_requires_confirmation_and_the_invitation_strength_rules(
        string $password,
        string $confirmation,
    ): void {
        $user = User::factory()->employee()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_reset_link_requests_are_throttled_after_five_attempts(): void
    {
        Notification::fake();
        $email = 'rate-limited@example.com';

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), ['email' => $email])
                ->assertRedirect()
                ->assertSessionHas('status', self::GENERIC_STATUS);
        }

        $this->post(route('password.email'), ['email' => $email])
            ->assertTooManyRequests();
        Notification::assertNothingSent();
    }

    /** @return array<string, array{string, string}> */
    public static function invalidPasswords(): array
    {
        return [
            'missing confirmation' => ['NewStrongPassword!123', ''],
            'confirmation mismatch' => ['NewStrongPassword!123', 'DifferentStrong!456'],
            'too short' => ['Short!1a', 'Short!1a'],
            'missing mixed case' => ['alllowercase!123', 'alllowercase!123'],
            'missing number' => ['StrongPassword!', 'StrongPassword!'],
            'missing symbol' => ['StrongPassword123', 'StrongPassword123'],
        ];
    }
}
