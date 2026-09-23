<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseSixOneMigrationRollbackTest extends TestCase
{
    private const CONNECTION = 'phase_six_one_migration_rollback';

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_work_arrangement_migrations_preserve_legacy_rows_and_roll_back_in_isolation(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'attendance-phase-six-one-');
        $this->assertNotFalse($databasePath);
        $this->databasePath = $databasePath;

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'transaction_mode' => 'DEFERRED',
        ]]);

        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);

        try {
            $baseMigrations = [
                require database_path('migrations/0001_01_01_000000_create_users_table.php'),
                require database_path('migrations/2026_09_23_174833_add_workforce_fields_to_users_table.php'),
                require database_path('migrations/2026_09_23_182821_create_departments_table.php'),
                require database_path('migrations/2026_09_23_182822_create_employees_table.php'),
                require database_path('migrations/2026_09_23_184656_replace_is_active_with_account_status_on_users_table.php'),
                require database_path('migrations/2026_09_23_192842_create_attendance_sessions_table.php'),
                require database_path('migrations/2026_09_23_200859_create_attendance_adjustments_table.php'),
            ];

            foreach ($baseMigrations as $migration) {
                $migration->up();
            }

            $administratorId = $this->insertUser('Migration Admin', 'migration-admin@example.test', 'admin');
            $employeeUserId = $this->insertUser('Migration Employee', 'migration-employee@example.test', 'employee');
            $employeeId = DB::table('employees')->insertGetId([
                'user_id' => $employeeUserId,
                'employee_number' => 'EMP-PHASE-6-1',
                'employment_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sessionId = DB::table('attendance_sessions')->insertGetId([
                'employee_id' => $employeeId,
                'work_date' => '2026-09-23',
                'time_in_at' => '2026-09-23 08:00:00',
                'time_out_at' => '2026-09-23 17:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $adjustmentId = DB::table('attendance_adjustments')->insertGetId([
                'attendance_session_id' => $sessionId,
                'administrator_id' => $administratorId,
                'reason' => 'Legacy adjustment before arrangements were recorded.',
                'previous_work_date' => '2026-09-23',
                'previous_time_in_at' => '2026-09-23 08:00:00',
                'previous_time_out_at' => '2026-09-23 17:00:00',
                'corrected_work_date' => '2026-09-23',
                'corrected_time_in_at' => '2026-09-23 08:05:00',
                'corrected_time_out_at' => '2026-09-23 17:00:00',
                'corrected_at' => '2026-09-23 18:00:00',
            ]);

            $sessionMigration = require database_path('migrations/2026_09_23_214332_add_work_arrangement_to_attendance_sessions_table.php');
            $adjustmentMigration = require database_path('migrations/2026_09_23_214333_add_work_arrangement_snapshots_to_attendance_adjustments_table.php');
            $sessionMigration->up();
            $adjustmentMigration->up();

            $this->assertTrue(Schema::hasColumn('attendance_sessions', 'work_arrangement'));
            $this->assertTrue(Schema::hasColumns('attendance_adjustments', [
                'before_work_arrangement', 'after_work_arrangement',
            ]));
            $this->assertNull(DB::table('attendance_sessions')->where('id', $sessionId)->value('work_arrangement'));
            $this->assertNull(DB::table('attendance_adjustments')->where('id', $adjustmentId)->value('before_work_arrangement'));
            $this->assertNull(DB::table('attendance_adjustments')->where('id', $adjustmentId)->value('after_work_arrangement'));

            $adjustmentMigration->down();
            $sessionMigration->down();

            $this->assertFalse(Schema::hasColumn('attendance_sessions', 'work_arrangement'));
            $this->assertFalse(Schema::hasColumn('attendance_adjustments', 'before_work_arrangement'));
            $this->assertFalse(Schema::hasColumn('attendance_adjustments', 'after_work_arrangement'));
            $this->assertSame(1, DB::table('attendance_sessions')->where('id', $sessionId)->count());
            $this->assertSame(1, DB::table('attendance_adjustments')->where('id', $adjustmentId)->count());
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }

    private function insertUser(string $name, string $email, string $role): int
    {
        return DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => 'not-used',
            'role' => $role,
            'account_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
