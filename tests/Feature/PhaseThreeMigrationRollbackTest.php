<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseThreeMigrationRollbackTest extends TestCase
{
    private const CONNECTION = 'phase_three_migration_rollback';

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_phase_three_migrations_roll_back_safely_in_an_isolated_database(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'attendance-phase-three-');
        $this->assertNotFalse($databasePath);
        $this->databasePath = $databasePath;

        config([
            'database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite',
                'url' => null,
                'database' => $databasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
                'busy_timeout' => null,
                'journal_mode' => null,
                'synchronous' => null,
                'transaction_mode' => 'DEFERRED',
            ],
        ]);

        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);

        try {
            $createUsers = require database_path('migrations/0001_01_01_000000_create_users_table.php');
            $addWorkforceFields = require database_path('migrations/2026_09_23_174833_add_workforce_fields_to_users_table.php');
            $createDepartments = require database_path('migrations/2026_09_23_182821_create_departments_table.php');
            $createEmployees = require database_path('migrations/2026_09_23_182822_create_employees_table.php');
            $replaceAccountStatus = require database_path('migrations/2026_09_23_184656_replace_is_active_with_account_status_on_users_table.php');
            $createInvitations = require database_path('migrations/2026_09_23_184657_create_employee_invitations_table.php');

            $createUsers->up();
            $addWorkforceFields->up();
            $createDepartments->up();
            $createEmployees->up();
            $replaceAccountStatus->up();
            $createInvitations->up();

            $userId = DB::table('users')->insertGetId([
                'name' => 'Pending Rollback User',
                'email' => 'rollback@example.test',
                'password' => null,
                'role' => 'employee',
                'account_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertTrue(Schema::hasTable('employee_invitations'));
            $this->assertTrue(Schema::hasColumn('users', 'account_status'));

            $createInvitations->down();
            $replaceAccountStatus->down();

            $rolledBackUser = DB::table('users')->find($userId);
            $this->assertFalse(Schema::hasTable('employee_invitations'));
            $this->assertFalse(Schema::hasColumn('users', 'account_status'));
            $this->assertTrue(Schema::hasColumn('users', 'is_active'));
            $this->assertSame(0, $rolledBackUser->is_active);
            $this->assertNotNull($rolledBackUser->password);
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
