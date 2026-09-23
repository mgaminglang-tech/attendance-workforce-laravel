<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class WorkforceAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_manage_workforce_master_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('manage-workforce'));
    }

    public function test_employee_cannot_manage_workforce_master_data(): void
    {
        $employee = User::factory()->employee()->create();

        $this->assertTrue(Gate::forUser($employee)->denies('manage-workforce'));
    }
}
