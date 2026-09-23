<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Mail\EmployeeInvitationMail;
use App\Models\Employee;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmployeeInvitationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_invitation_allows_password_setup_and_invalidates_token(): void
    {
        [$user, $invitation, $token] = $this->pendingEmployeeWithInvitation();

        $this->get(route('invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertSee($user->name);

        $response = $this->post(route('invitations.accept', ['token' => $token]), [
            'password' => 'StrongPassword!123',
            'password_confirmation' => 'StrongPassword!123',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account is active. You may now sign in.');
        $this->assertSame(AccountStatus::Active, $user->refresh()->account_status);
        $this->assertTrue(Hash::check('StrongPassword!123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($invitation->refresh()->accepted_at);
        $this->get(route('invitations.show', ['token' => $token]))->assertNotFound();
    }

    public function test_activated_employee_can_login(): void
    {
        [$user, , $token] = $this->pendingEmployeeWithInvitation();
        $this->post(route('invitations.accept', ['token' => $token]), [
            'password' => 'StrongPassword!123',
            'password_confirmation' => 'StrongPassword!123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'StrongPassword!123',
        ]);

        $response->assertRedirect(route('employee.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        [, , $token] = $this->pendingEmployeeWithInvitation(expiresAt: now()->subMinute());

        $this->get(route('invitations.show', ['token' => $token]))->assertNotFound();
        $this->post(route('invitations.accept', ['token' => $token]), [
            'password' => 'StrongPassword!123',
            'password_confirmation' => 'StrongPassword!123',
        ])->assertNotFound();
    }

    public function test_already_used_invitation_is_rejected(): void
    {
        [, , $token] = $this->pendingEmployeeWithInvitation();
        $payload = [
            'password' => 'StrongPassword!123',
            'password_confirmation' => 'StrongPassword!123',
        ];
        $this->post(route('invitations.accept', ['token' => $token]), $payload)->assertRedirect(route('login'));

        $this->post(route('invitations.accept', ['token' => $token]), $payload)->assertNotFound();
    }

    public function test_invitation_cannot_activate_another_user(): void
    {
        [$invitedUser, , $token] = $this->pendingEmployeeWithInvitation();
        $otherUser = User::factory()->employee()->pending()->create();

        $this->post(route('invitations.accept', ['token' => $token]), [
            'user_id' => $otherUser->id,
            'email' => $otherUser->email,
            'password' => 'StrongPassword!123',
            'password_confirmation' => 'StrongPassword!123',
        ])->assertRedirect(route('login'));

        $this->assertSame(AccountStatus::Active, $invitedUser->refresh()->account_status);
        $this->assertSame(AccountStatus::Pending, $otherUser->refresh()->account_status);
        $this->assertNull($otherUser->password);
    }

    public function test_password_setup_requires_confirmation_and_a_strong_password(): void
    {
        [, , $token] = $this->pendingEmployeeWithInvitation();

        $this->post(route('invitations.accept', ['token' => $token]), [
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_can_resend_pending_invitation_and_previous_token_becomes_invalid(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-23 09:00:00');
        [$user, $invitation, $oldToken] = $this->pendingEmployeeWithInvitation();
        $employee = $user->employee;
        $oldHash = $invitation->token_hash;
        $admin = User::factory()->admin()->create();
        $this->travel(61)->seconds();

        $this->actingAs($admin)
            ->post(route('admin.employees.invitation.store', $employee))
            ->assertRedirect();

        $invitation->refresh();
        $this->assertNotSame($oldHash, $invitation->token_hash);
        $this->assertSame(1, EmployeeInvitation::query()->whereBelongsTo($user)->count());

        $newToken = '';
        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) use (&$newToken, $user): bool {
            $newToken = $mail->token;

            return $mail->user->is($user);
        });

        auth()->logout();
        $this->get(route('invitations.show', ['token' => $oldToken]))->assertNotFound();
        $this->get(route('invitations.show', ['token' => $newToken]))->assertOk();
    }

    public function test_resend_cooldown_rejects_rapid_repeat_attempt(): void
    {
        Mail::fake();
        [$user] = $this->pendingEmployeeWithInvitation();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.employees.show', $user->employee))
            ->post(route('admin.employees.invitation.store', $user->employee))
            ->assertSessionHasErrors('invitation');

        Mail::assertNothingSent();
    }

    /**
     * @return array{User, EmployeeInvitation, string}
     */
    private function pendingEmployeeWithInvitation(mixed $expiresAt = null): array
    {
        $token = bin2hex(random_bytes(32));
        $user = User::factory()->employee()->pending()->create();
        Employee::factory()->for($user)->create();
        $invitation = EmployeeInvitation::factory()->for($user)->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt ?? now()->addHours(48),
        ]);

        return [$user, $invitation, $token];
    }
}
