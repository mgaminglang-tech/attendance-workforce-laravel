<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseFiveMigrationRollbackTest extends TestCase
{
    private const CONNECTION = 'phase_five_migration_rollback';

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_adjustment_migration_rolls_back_without_removing_attendance_sessions(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'attendance-phase-five-');
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
            $migrations = [
                require database_path('migrations/0001_01_01_000000_create_users_table.php'),
                require database_path('migrations/2026_09_23_174833_add_workforce_fields_to_users_table.php'),
                require database_path('migrations/2026_09_23_182821_create_departments_table.php'),
                require database_path('migrations/2026_09_23_182822_create_employees_table.php'),
                require database_path('migrations/2026_09_23_184656_replace_is_active_with_account_status_on_users_table.php'),
                require database_path('migrations/2026_09_23_192842_create_attendance_sessions_table.php'),
            ];

            foreach ($migrations as $migration) {
                $migration->up();
            }

            $adjustmentMigration = require database_path('migrations/2026_09_23_200859_create_attendance_adjustments_table.php');
            $adjustmentMigration->up();

            $this->assertTrue(Schema::hasColumns('attendance_adjustments', [
                'attendance_session_id',
                'administrator_id',
                'reason',
                'previous_work_date',
                'previous_time_in_at',
                'previous_time_out_at',
                'corrected_work_date',
                'corrected_time_in_at',
                'corrected_time_out_at',
                'corrected_at',
            ]));

            $adjustmentMigration->down();

            $this->assertFalse(Schema::hasTable('attendance_adjustments'));
            $this->assertTrue(Schema::hasTable('attendance_sessions'));
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
