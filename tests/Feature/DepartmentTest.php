<?php

namespace Tests\Feature;

use App\Models\Department;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_department_can_be_created_with_valid_data(): void
    {
        $department = Department::create([
            'name' => 'Information Technology',
            'code' => 'IT',
            'is_active' => true,
        ]);

        $this->assertModelExists($department);
        $this->assertSame('Information Technology', $department->name);
        $this->assertSame('IT', $department->code);
        $this->assertTrue($department->is_active);
    }

    public function test_duplicate_department_code_is_rejected_by_the_database(): void
    {
        Department::factory()->create(['code' => 'OPS']);

        $this->expectException(QueryException::class);

        Department::factory()->create(['code' => 'OPS']);
    }

    public function test_duplicate_department_name_is_rejected_by_the_database(): void
    {
        Department::factory()->create(['name' => 'Operations']);

        $this->expectException(QueryException::class);

        Department::factory()->create(['name' => 'Operations']);
    }
}
