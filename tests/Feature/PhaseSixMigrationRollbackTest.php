<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseSixMigrationRollbackTest extends TestCase
{
    private const CONNECTION = 'phase_six_migration_rollback';

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_assignment_migration_rolls_back_without_removing_users_or_departments(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'attendance-phase-six-');
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
            $createUsers = require database_path('migrations/0001_01_01_000000_create_users_table.php');
            $addWorkforceFields = require database_path('migrations/2026_09_23_174833_add_workforce_fields_to_users_table.php');
            $createDepartments = require database_path('migrations/2026_09_23_182821_create_departments_table.php');
            $replaceAccountStatus = require database_path('migrations/2026_09_23_184656_replace_is_active_with_account_status_on_users_table.php');
            $assignmentMigration = require database_path('migrations/2026_09_23_204044_create_department_hr_assignments_table.php');

            $createUsers->up();
            $addWorkforceFields->up();
            $createDepartments->up();
            $replaceAccountStatus->up();
            $assignmentMigration->up();

            $administratorId = DB::table('users')->insertGetId([
                'name' => 'Global Admin',
                'email' => 'phase-six-admin@example.test',
                'password' => 'not-used',
                'role' => 'admin',
                'account_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $representativeId = DB::table('users')->insertGetId([
                'name' => 'HR Representative',
                'email' => 'phase-six-hr@example.test',
                'password' => 'not-used',
                'role' => 'employee',
                'account_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $departmentId = DB::table('departments')->insertGetId([
                'name' => 'Pilot Department',
                'code' => 'PILOT',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('department_hr_assignments')->insert([
                'department_id' => $departmentId,
                'user_id' => $representativeId,
                'assigned_by_user_id' => $administratorId,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertTrue(Schema::hasColumns('department_hr_assignments', [
                'department_id', 'user_id', 'assigned_by_user_id', 'assigned_at',
            ]));

            $assignmentMigration->down();

            $this->assertFalse(Schema::hasTable('department_hr_assignments'));
            $this->assertTrue(Schema::hasTable('users'));
            $this->assertTrue(Schema::hasTable('departments'));
            $this->assertSame(2, DB::table('users')->count());
            $this->assertSame(1, DB::table('departments')->count());
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
