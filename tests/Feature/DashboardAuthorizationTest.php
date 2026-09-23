<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_employee_dashboard(): void
    {
        $this->get(route('employee.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_employee_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_employee_can_access_employee_dashboard(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Employee Dashboard');
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin Dashboard');
    }

    public function test_admin_cannot_access_employee_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertForbidden();
    }
}
