<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseFourMigrationRollbackTest extends TestCase
{
    private const CONNECTION = 'phase_four_migration_rollback';

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_attendance_migration_rolls_back_safely_in_an_isolated_database(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'attendance-phase-four-');
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
            $createAttendanceSessions = require database_path('migrations/2026_09_23_192842_create_attendance_sessions_table.php');

            $createUsers->up();
            $addWorkforceFields->up();
            $createDepartments->up();
            $createEmployees->up();
            $replaceAccountStatus->up();
            $createAttendanceSessions->up();

            $userId = DB::table('users')->insertGetId([
                'name' => 'Migration Employee',
                'email' => 'phase-four@example.test',
                'password' => 'not-used',
                'role' => 'employee',
                'account_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $employeeId = DB::table('employees')->insertGetId([
                'user_id' => $userId,
                'employee_number' => 'EMP-PHASE-4',
                'employment_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('attendance_sessions')->insert([
                'employee_id' => $employeeId,
                'work_date' => '2026-09-23',
                'time_in_at' => '2026-09-23 08:00:00',
                'time_out_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertTrue(Schema::hasColumns('attendance_sessions', [
                'employee_id',
                'work_date',
                'time_in_at',
                'time_out_at',
            ]));

            $createAttendanceSessions->down();

            $this->assertFalse(Schema::hasTable('attendance_sessions'));
            $this->assertTrue(Schema::hasTable('employees'));
            $this->assertSame(1, DB::table('employees')->where('id', $employeeId)->count());
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
