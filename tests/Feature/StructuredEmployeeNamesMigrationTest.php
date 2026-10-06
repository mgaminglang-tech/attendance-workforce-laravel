<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StructuredEmployeeNamesMigrationTest extends TestCase
{
    private const CONNECTION = 'structured_employee_names_migration';

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_upgrade_keeps_legacy_names_null_and_rollback_removes_only_the_new_columns(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'attendance-structured-names-');
        $this->assertNotFalse($databasePath);
        $this->databasePath = $databasePath;
        config([
            'database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite', 'url' => null, 'database' => $databasePath,
                'prefix' => '', 'foreign_key_constraints' => true,
                'busy_timeout' => null, 'journal_mode' => null, 'synchronous' => null,
                'transaction_mode' => 'DEFERRED',
            ],
        ]);
        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);

        try {
            foreach ([
                '0001_01_01_000000_create_users_table.php',
                '2026_09_23_174833_add_workforce_fields_to_users_table.php',
                '2026_09_23_182821_create_departments_table.php',
                '2026_09_23_182822_create_employees_table.php',
                '2026_09_23_184656_replace_is_active_with_account_status_on_users_table.php',
            ] as $filename) {
                (require database_path('migrations/'.$filename))->up();
            }

            $user = User::factory()->employee()->create(['name' => 'Ana Marie De la Cruz Jr.']);
            $employee = Employee::factory()->for($user)->create(['employee_number' => 'EMP-LEGACY']);
            $originalEmployee = (array) DB::table('employees')->where('id', $employee->id)->first();
            $originalUser = (array) DB::table('users')->where('id', $user->id)->first();
            $originalColumns = Schema::getColumnListing('employees');
            $originalUserColumns = Schema::getColumnListing('users');
            $migration = require database_path('migrations/2026_10_06_161523_add_structured_names_to_employees_table.php');

            $migration->up();

            $upgradedEmployee = (array) DB::table('employees')->where('id', $employee->id)->first();
            $this->assertSame(array_merge($originalEmployee, ['first_name' => null, 'last_name' => null]), $upgradedEmployee);
            $this->assertSame($originalUser, (array) DB::table('users')->where('id', $user->id)->first());
            $this->assertTrue(Schema::hasColumns('employees', ['first_name', 'last_name']));

            DB::table('employees')->where('id', $employee->id)->update([
                'first_name' => 'Ana Marie', 'last_name' => 'De la Cruz Jr.',
            ]);

            $migration->down();

            $this->assertSame($originalColumns, Schema::getColumnListing('employees'));
            $this->assertSame($originalUserColumns, Schema::getColumnListing('users'));
            $this->assertSame($originalEmployee, (array) DB::table('employees')->where('id', $employee->id)->first());
            $this->assertSame($originalUser, (array) DB::table('users')->where('id', $user->id)->first());
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
