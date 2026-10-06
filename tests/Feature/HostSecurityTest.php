<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class HostSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ORIGIN = 'https://workforce.example.test';

    private const CSRF_TOKEN = 'host-security-test-csrf';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertDatabaseCount('users', 0);
        $this->app->instance('env', 'production');
        config(['app.env' => 'production', 'app.url' => self::ORIGIN, 'logging.default' => 'null']);
        $this->withSession(['_token' => self::CSRF_TOKEN]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        TrustHosts::flushState();

        parent::tearDown();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_configured_host_sends_reset_link_without_trusting_forwarded_host(bool $forwardedHost): void
    {
        Notification::fake();
        $user = User::factory()->employee()->create();
        if ($forwardedHost) {
            $this->withHeader('X-Forwarded-Host', 'evil.example.test');
        }

        $this->post(self::ORIGIN.'/forgot-password', [
            'email' => $user->email,
            '_token' => self::CSRF_TOKEN,
        ])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertSame('workforce.example.test', parse_url($url, PHP_URL_HOST));
            $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));

            return true;
        });
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    #[DataProvider('untrustedHosts')]
    public function test_untrusted_host_is_rejected_before_password_recovery(string $host): void
    {
        Notification::fake();
        $user = User::factory()->employee()->create();

        $this->post('https://'.$host.'/forgot-password', [
            'email' => $user->email,
            '_token' => self::CSRF_TOKEN,
        ])->assertBadRequest();

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_admin_invitation_uses_configured_host_in_both_bodies(bool $forwardedHost): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        if ($forwardedHost) {
            $this->withHeader('X-Forwarded-Host', 'evil.example.test');
        }

        $this->actingAs($admin)->post(self::ORIGIN.'/admin/employees', [
            'first_name' => 'Invited',
            'last_name' => 'Employee',
            'email' => 'invited@example.test',
            'employee_number' => 'EMP-HOST',
            '_token' => self::CSRF_TOKEN,
        ])->assertRedirect();

        $token = null;
        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) use (&$token): bool {
            $token = $mail->token;
            $url = $mail->content()->with['activationUrl'];
            $this->assertSame('workforce.example.test', parse_url($url, PHP_URL_HOST));
            $mail->assertSeeInHtml($url);
            $mail->assertSeeInText($url);
            $mail->assertDontSeeInHtml('evil.example.test');
            $mail->assertDontSeeInText('evil.example.test');

            return $mail->hasTo('invited@example.test');
        });
        $this->assertDatabaseHas('users', ['email' => 'invited@example.test', 'account_status' => 'pending']);

        auth()->logout();
        $this->post(self::ORIGIN.'/invitations/'.$token, [
            'password' => 'StrongPassword!123',
            'password_confirmation' => 'StrongPassword!123',
            '_token' => self::CSRF_TOKEN,
        ])->assertRedirect(self::ORIGIN.'/login');
        $this->assertDatabaseHas('users', ['email' => 'invited@example.test', 'account_status' => 'active']);
    }

    public function test_untrusted_host_cannot_trigger_authenticated_admin_invitation(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('https://evil.example.test/admin/employees', [
            'first_name' => 'Invited',
            'last_name' => 'Employee',
            'email' => 'invited@example.test',
            'employee_number' => 'EMP-HOST',
            '_token' => self::CSRF_TOKEN,
        ])->assertBadRequest();

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'invited@example.test']);
        $this->assertDatabaseCount('employee_invitations', 0);
    }

    #[DataProvider('invalidApplicationUrls')]
    public function test_invalid_production_application_url_fails_closed(string $url): void
    {
        Notification::fake();
        config(['app.url' => $url]);

        $this->get(self::ORIGIN.'/forgot-password')->assertServiceUnavailable();

        Notification::assertNothingSent();
    }

    public function test_local_and_testing_hosts_remain_usable(): void
    {
        foreach (['local', 'testing'] as $environment) {
            $this->app->instance('env', $environment);
            config(['app.env' => $environment, 'app.url' => 'http://localhost:8000']);
            Request::setTrustedHosts([]);

            $this->get('http://localhost:8000/login')->assertOk();
        }
    }

    public function test_password_reset_login_and_logout_work_on_the_configured_host(): void
    {
        $user = User::factory()->employee()->create();
        $token = Password::createToken($user);

        $this->post(self::ORIGIN.'/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrongPassword!123',
            'password_confirmation' => 'NewStrongPassword!123',
            '_token' => self::CSRF_TOKEN,
        ])->assertRedirect(self::ORIGIN.'/login');
        $this->assertGuest();

        $this->post(self::ORIGIN.'/login', [
            'email' => $user->email,
            'password' => 'NewStrongPassword!123',
            '_token' => self::CSRF_TOKEN,
        ])->assertRedirect(self::ORIGIN.'/employee/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post(self::ORIGIN.'/logout', ['_token' => session()->token()])
            ->assertRedirect(self::ORIGIN.'/login');
        $this->assertGuest();
    }

    public function test_signed_urls_remain_valid_for_the_configured_host(): void
    {
        Route::middleware(['web', 'signed'])->get('/host-security-signed', fn (): string => 'Valid signature')
            ->name('host-security.signed');
        Route::getRoutes()->refreshNameLookups();
        $this->get(self::ORIGIN.'/login')->assertOk();
        $url = URL::temporarySignedRoute('host-security.signed', now()->addMinutes(5));

        $this->assertSame('workforce.example.test', parse_url($url, PHP_URL_HOST));
        $this->get($url)->assertOk()->assertSee('Valid signature');
    }

    /** @return array<string, array{string}> */
    public static function untrustedHosts(): array
    {
        return [
            'hostile' => ['evil.example.test'],
            'subdomain' => ['hr.workforce.example.test'],
            'regex lookalike' => ['workforceXexampleXtest'],
            'suffix' => ['workforce.example.test.evil.example.test'],
            'raw IP' => ['127.0.0.1'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidApplicationUrls(): array
    {
        return [
            'empty' => [''],
            'missing hostname' => ['https://'],
            'HTTP' => ['http://workforce.example.test'],
            'invalid hostname' => ['https://bad_host.example.test'],
            'IP address' => ['https://127.0.0.1'],
            'userinfo' => ['https://user@workforce.example.test'],
            'query' => ['https://workforce.example.test/?host=evil.example.test'],
            'fragment' => ['https://workforce.example.test/#fragment'],
        ];
    }
}
