<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_is_accessible_to_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Email address')
            ->assertSee('Remember me');
    }

    public function test_authenticated_user_is_redirected_away_from_login_page(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('employee.dashboard'));
    }

    public function test_active_employee_can_authenticate(): void
    {
        $user = User::factory()->employee()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('employee.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_login_regenerates_the_session_identifier(): void
    {
        $user = User::factory()->employee()->create();
        $this->withSession(['session_marker' => 'preserved']);
        $previousSessionId = session()->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('employee.dashboard'));

        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertSame('preserved', session('session_marker'));
    }

    public function test_active_admin_can_authenticate(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_remember_me_creates_a_persistent_login_token(): void
    {
        $user = User::factory()->create(['remember_token' => null]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect(route('employee.dashboard'));
        $this->assertNotNull($user->refresh()->remember_token);
    }

    public function test_invalid_credentials_are_rejected_with_a_generic_message(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
        $this->assertGuest();
        $this->assertNull($user->refresh()->last_login_at);
    }

    public function test_inactive_account_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
        $this->assertGuest();
        $this->assertNull($user->refresh()->last_login_at);
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->withSession(['session_marker' => 'remove-me']);
        $previousSessionId = session()->getId();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertNull(session('session_marker'));
    }

    public function test_public_registration_routes_do_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $this->post(route('login.store'))
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        RateLimiter::clear('throttled@example.com|127.0.0.1');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'throttled@example.com',
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => 'throttled@example.com',
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email', 'Too many login attempts. Please try again in');
    }
}
