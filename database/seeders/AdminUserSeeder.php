<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('AdminUserSeeder may only run in the local environment.');
        }

        $credentials = Validator::make(config('workforce.local_admin'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        $user = User::firstOrNew(['email' => $credentials['email']]);
        $user->forceFill([
            'name' => $credentials['name'],
            'password' => Hash::make($credentials['password']),
            'role' => UserRole::Admin,
            'is_active' => true,
        ])->save();

        $this->command?->info("Local administrator ready: {$user->email}");
    }
}
