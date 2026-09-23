<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRollbackTest extends TestCase
{
    private const CONNECTION = 'migration_rollback';

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_phase_two_migrations_roll_back_safely_in_an_isolated_database(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'attendance-migrations-');
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

            $createUsers->up();
            $addWorkforceFields->up();
            $createDepartments->up();
            $createEmployees->up();

            $this->assertTrue(Schema::connection(self::CONNECTION)->hasTable('departments'));
            $this->assertTrue(Schema::connection(self::CONNECTION)->hasTable('employees'));

            $createEmployees->down();
            $createDepartments->down();

            $this->assertFalse(Schema::connection(self::CONNECTION)->hasTable('employees'));
            $this->assertFalse(Schema::connection(self::CONNECTION)->hasTable('departments'));
            $this->assertTrue(Schema::connection(self::CONNECTION)->hasTable('users'));
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
