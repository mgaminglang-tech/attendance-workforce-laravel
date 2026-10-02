<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Question\Question;
use Throwable;

#[Signature('app:create-global-admin')]
#[Description('Interactively create an active Global Admin without default credentials')]
class CreateGlobalAdmin extends Command
{
    public function handle(): int
    {
        if (PHP_SAPI !== 'cli' || ! $this->input->isInteractive() || ! $this->hasInteractiveTerminal()) {
            $this->error('An interactive terminal with hidden password input is required. No account was created.');

            return self::FAILURE;
        }

        try {
            $additionalAdminConfirmed = false;

            if (User::query()->where('role', UserRole::Admin)->exists()) {
                $this->warn('A Global Admin account already exists. Creating another grants full administrative access.');

                if (! $this->confirm('Create another Global Admin?', false)) {
                    $this->info('No account was created.');

                    return self::FAILURE;
                }

                $additionalAdminConfirmed = true;
            }

            $name = Str::of((string) $this->ask('Full name'))->trim()->toString();
            $email = Str::of((string) $this->ask('Email'))->trim()->lower()->toString();

            Validator::make([
                'name' => $name,
                'email' => $email,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            ])->validate();

            $password = $this->askHiddenPassword('Password');
            $passwordConfirmation = $this->askHiddenPassword('Confirm password');

            Validator::make([
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ], [
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            ])->validate();

            $user = Cache::store('database')->lock('global-admin-bootstrap', 120)->block(10, function () use ($name, $email, $password, $additionalAdminConfirmed): ?User {
                return DB::transaction(function () use ($name, $email, $password, $additionalAdminConfirmed): ?User {
                    if (! $additionalAdminConfirmed && User::query()->where('role', UserRole::Admin)->exists()) {
                        return null;
                    }

                    $user = new User;
                    $user->forceFill([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($password),
                        'role' => UserRole::Admin,
                        'account_status' => AccountStatus::Active,
                    ])->save();

                    return $user;
                });
            });

            if ($user === null) {
                $this->error('A Global Admin was created during this session. Rerun the command to confirm an additional account.');

                return self::FAILURE;
            }
        } catch (ValidationException $exception) {
            $this->showValidationErrors($exception);

            return self::FAILURE;
        } catch (ConsoleRuntimeException) {
            $this->error('Password input could not be hidden. No account was created.');

            return self::FAILURE;
        } catch (LockTimeoutException) {
            $this->error('Another Global Admin creation is in progress. No account was created; retry after it finishes.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Global Admin creation could not be confirmed. Check existing accounts and database connectivity before retrying.');

            return self::FAILURE;
        }

        $this->info('Global Admin account created.');

        return self::SUCCESS;
    }

    protected function hasInteractiveTerminal(): bool
    {
        return defined('STDIN') && stream_isatty(STDIN);
    }

    private function askHiddenPassword(string $label): string
    {
        $question = (new Question($label))
            ->setHidden(true)
            ->setHiddenFallback(false)
            ->setTrimmable(false);

        return (string) $this->output->askQuestion($question);
    }

    private function showValidationErrors(ValidationException $exception): void
    {
        foreach (array_keys($exception->errors()) as $field) {
            $this->error(match ($field) {
                'name' => 'Full name is required and must be at most 255 characters.',
                'email' => 'Enter a valid email address that is not already in use.',
                'password' => 'Password must have at least 12 characters, mixed case, a number, a symbol, and a matching confirmation.',
                default => 'Invalid input. No account was created.',
            });
        }
    }
}
