<?php

namespace Tests\Feature;

use App\Console\Commands\CreateGlobalAdmin;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\Question;
use Tests\TestCase;

class CreateGlobalAdminCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_first_global_admin_is_active_can_log_in_and_has_no_employee_profile(): void
    {
        $password = 'StrongAdminPassword!123';
        $this->useTerminal();

        $this->createGlobalAdmin('  Example Admin  ', '  ADMIN@EXAMPLE.TEST  ', $password);

        $admin = User::query()->sole();
        $this->assertSame('Example Admin', $admin->name);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(AccountStatus::Active, $admin->account_status);
        $this->assertNotSame($password, $admin->password);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertNull($admin->employee);
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, EmployeeInvitation::count());

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => $password,
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_duplicate_email_is_rejected_before_password_prompt(): void
    {
        User::factory()->employee()->create(['email' => 'admin@example.test']);
        $this->useTerminal();

        $this->artisan('app:create-global-admin')
            ->expectsQuestion('Full name', 'New Admin')
            ->expectsQuestion('Email', '  ADMIN@EXAMPLE.TEST  ')
            ->expectsOutput('Enter a valid email address that is not already in use.')
            ->assertExitCode(1);

        $this->assertSame(1, User::count());
        $this->assertDatabaseMissing('users', ['name' => 'New Admin']);
    }

    public function test_password_prompts_require_hidden_input_without_visible_fallback(): void
    {
        $output = new class(new ArrayInput([]), new BufferedOutput) extends OutputStyle
        {
            /** @var list<Question> */
            public array $questions = [];

            public function askQuestion(Question $question): mixed
            {
                $this->questions[] = $question;

                return match ($question->getQuestion()) {
                    'Full name' => 'New Admin',
                    'Email' => 'admin@example.test',
                    'Password', 'Confirm password' => 'StrongAdminPassword!123',
                    default => throw new RuntimeException('Unexpected question.'),
                };
            }
        };
        $command = $this->useTerminal();
        $command->setLaravel($this->app);
        $input = new ArrayInput([], $command->getDefinition());
        $input->setInteractive(true);

        $this->assertSame(0, $command->run($input, $output));

        $this->assertSame(
            ['Full name', 'Email', 'Password', 'Confirm password'],
            array_map(fn (Question $question): string => $question->getQuestion(), $output->questions),
        );

        foreach (array_slice($output->questions, 2) as $question) {
            $this->assertTrue($question->isHidden());
            $this->assertFalse($question->isHiddenFallback());
            $this->assertFalse($question->isTrimmable());
        }
    }

    public function test_invalid_email_is_rejected_before_password_prompt(): void
    {
        $this->useTerminal();

        $this->artisan('app:create-global-admin')
            ->expectsQuestion('Full name', 'New Admin')
            ->expectsQuestion('Email', 'invalid-email')
            ->expectsOutput('Enter a valid email address that is not already in use.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_blank_full_name_is_rejected_before_password_prompt(): void
    {
        $this->useTerminal();

        $this->artisan('app:create-global-admin')
            ->expectsQuestion('Full name', '  ')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsOutput('Full name is required and must be at most 255 characters.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('invalidPasswords')]
    public function test_weak_or_unconfirmed_password_is_rejected(string $password, string $confirmation): void
    {
        $this->useTerminal();

        $this->artisan('app:create-global-admin')
            ->expectsQuestion('Full name', 'New Admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $confirmation)
            ->expectsOutput('Password must have at least 12 characters, mixed case, a number, a symbol, and a matching confirmation.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidPasswords(): array
    {
        return [
            'short' => ['Short!1', 'Short!1'],
            'no uppercase' => ['lowercaseonly!123', 'lowercaseonly!123'],
            'no lowercase' => ['UPPERCASEONLY!123', 'UPPERCASEONLY!123'],
            'no number' => ['StrongPassword!!', 'StrongPassword!!'],
            'no symbol' => ['StrongPassword123', 'StrongPassword123'],
            'confirmation mismatch' => ['StrongAdminPassword!123', 'DifferentPassword!123'],
        ];
    }

    public function test_existing_global_admin_requires_explicit_confirmation(): void
    {
        User::factory()->admin()->disabled()->create();
        $this->useTerminal();

        $this->artisan('app:create-global-admin')
            ->expectsOutput('A Global Admin account already exists. Creating another grants full administrative access.')
            ->expectsConfirmation('Create another Global Admin?', 'no')
            ->expectsOutput('No account was created.')
            ->assertExitCode(1);

        $this->assertSame(1, User::count());
    }

    public function test_additional_global_admin_can_be_created_after_confirmation(): void
    {
        User::factory()->admin()->create();
        $this->useTerminal();

        $this->createGlobalAdmin('Second Admin', 'second@example.test', 'StrongAdminPassword!123', confirmAdditionalAdmin: true);

        $this->assertSame(2, User::query()->where('role', UserRole::Admin)->count());
        $additionalAdmin = User::query()->where('email', 'second@example.test')->sole();
        $this->assertSame(AccountStatus::Active, $additionalAdmin->account_status);
        $this->assertNull($additionalAdmin->employee);
    }

    public function test_admin_created_during_prompts_requires_a_new_confirmation(): void
    {
        $buffer = new BufferedOutput;
        $output = new class(new ArrayInput([]), $buffer) extends OutputStyle
        {
            public function askQuestion(Question $question): mixed
            {
                return match ($question->getQuestion()) {
                    'Full name' => 'New Admin',
                    'Email' => 'admin@example.test',
                    'Password' => 'StrongAdminPassword!123',
                    'Confirm password' => $this->createAnotherAdmin(),
                    default => throw new RuntimeException('Unexpected question.'),
                };
            }

            private function createAnotherAdmin(): string
            {
                User::factory()->admin()->create(['email' => 'other@example.test']);

                return 'StrongAdminPassword!123';
            }
        };
        $command = $this->useTerminal();
        $command->setLaravel($this->app);
        $input = new ArrayInput([], $command->getDefinition());
        $input->setInteractive(true);

        $this->assertSame(1, $command->run($input, $output));

        $this->assertStringContainsString('A Global Admin was created during this session.', $buffer->fetch());
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    }

    public function test_user_creation_failure_rolls_back_and_hides_exception_details(): void
    {
        User::created(function (User $user): void {
            if ($user->email === 'admin@example.test') {
                throw new RuntimeException('Sensitive failure detail');
            }
        });
        $this->useTerminal();

        $this->artisan('app:create-global-admin')
            ->expectsQuestion('Full name', 'New Admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password', 'StrongAdminPassword!123')
            ->expectsQuestion('Confirm password', 'StrongAdminPassword!123')
            ->expectsOutput('Global Admin creation could not be confirmed. Check existing accounts and database connectivity before retrying.')
            ->doesntExpectOutputToContain('Sensitive failure detail')
            ->doesntExpectOutputToContain('StrongAdminPassword!123')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, EmployeeInvitation::count());
    }

    public function test_non_interactive_execution_creates_no_account(): void
    {
        $this->artisan('app:create-global-admin', ['--no-interaction' => true])
            ->expectsOutput('An interactive terminal with hidden password input is required. No account was created.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_interactive_terminal_creates_no_account(): void
    {
        $this->useTerminal(false);

        $this->artisan('app:create-global-admin')
            ->expectsOutput('An interactive terminal with hidden password input is required. No account was created.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_public_registration_and_admin_bootstrap_endpoints_do_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->get('/app/create-global-admin')->assertNotFound();
        $this->post('/app/create-global-admin')->assertNotFound();
    }

    private function createGlobalAdmin(string $name, string $email, string $password, bool $confirmAdditionalAdmin = false): void
    {
        $command = $this->artisan('app:create-global-admin');

        if ($confirmAdditionalAdmin) {
            $command
                ->expectsOutput('A Global Admin account already exists. Creating another grants full administrative access.')
                ->expectsConfirmation('Create another Global Admin?', 'yes');
        }

        $command
            ->expectsQuestion('Full name', $name)
            ->expectsQuestion('Email', $email)
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('Global Admin account created.')
            ->doesntExpectOutputToContain($password)
            ->assertExitCode(0)
            ->run();
    }

    private function useTerminal(bool $available = true): CreateGlobalAdmin
    {
        $command = new #[Signature('app:create-global-admin')] class($available) extends CreateGlobalAdmin
        {
            public function __construct(private bool $available)
            {
                parent::__construct();
            }

            protected function hasInteractiveTerminal(): bool
            {
                return $this->available;
            }
        };

        $this->app->instance(CreateGlobalAdmin::class, $command);

        return $command;
    }
}
