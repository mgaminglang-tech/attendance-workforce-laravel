<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseTwelveMigrationRollbackTest extends TestCase
{
    private const CONNECTION = 'phase_twelve_migration_rollback';

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_leave_migration_rolls_back_safely_without_removing_employee_data(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'attendance-phase-twelve-');
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
            $createLeaveDays = require database_path('migrations/2026_09_25_133933_create_employee_leave_days_table.php');

            $createUsers->up();
            $addWorkforceFields->up();
            $createDepartments->up();
            $createEmployees->up();
            $replaceAccountStatus->up();
            $createLeaveDays->up();

            $userId = DB::table('users')->insertGetId([
                'name' => 'Migration Employee',
                'email' => 'phase-twelve@example.test',
                'password' => 'not-used',
                'role' => 'employee',
                'account_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $employeeId = DB::table('employees')->insertGetId([
                'user_id' => $userId,
                'employee_number' => 'EMP-PHASE-12',
                'employment_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('employee_leave_days')->insert([
                'employee_id' => $employeeId,
                'leave_date' => '2026-09-25',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertTrue(Schema::hasColumns('employee_leave_days', [
                'employee_id',
                'leave_date',
            ]));

            $createLeaveDays->down();

            $this->assertFalse(Schema::hasTable('employee_leave_days'));
            $this->assertTrue(Schema::hasTable('employees'));
            $this->assertSame(1, DB::table('employees')->where('id', $employeeId)->count());
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
