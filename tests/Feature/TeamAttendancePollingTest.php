<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TeamAttendancePollingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_polling_endpoints_require_authentication(): void
    {
        $department = Department::factory()->create();

        $this->getJson(route('team-attendance.status'))->assertUnauthorized();
        $this->getJson(route('hr.team-attendance.status'))->assertUnauthorized();
        $this->getJson(route('admin.departments.team-attendance.status', $department))->assertUnauthorized();
    }

    public function test_employee_polling_is_server_scoped_and_contains_only_operational_fields(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00:00', 'Asia/Manila'));
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        [$viewer] = $this->employeeIn($finance, 'Finance Viewer');
        [, $financeMember] = $this->employeeIn($finance, 'Finance Member');
        [, $itMember] = $this->employeeIn($it, 'IT Private Member');
        AttendanceSession::factory()->for($financeMember)->open()->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
        ]);
        AttendanceSession::factory()->for($itMember)->open()->create([
            'work_date' => '2026-09-24',
            'time_in_at' => '2026-09-24 08:00:00',
        ]);

        $response = $this->actingAs($viewer)->getJson(route('team-attendance.status', [
            'department_id' => $it->id,
        ]))->assertOk()->assertJsonPath('department.name', 'Finance');
        $payload = $response->json();

        $this->assertSame(['department', 'summary', 'members', 'last_updated'], array_keys($payload));
        $this->assertSame(
            ['employee_name', 'employee_number', 'status', 'time_in', 'time_out', 'work_date'],
            array_keys($payload['members'][0]),
        );
        $this->assertContains('Finance Member', array_column($payload['members'], 'employee_name'));
        $this->assertNotContains('IT Private Member', array_column($payload['members'], 'employee_name'));
        $this->assertArrayNotHasKey('email', $payload['members'][0]);
        $this->assertArrayNotHasKey('correction_reason', $payload['members'][0]);
    }

    public function test_hr_polling_uses_assignment_scope_instead_of_employee_department(): void
    {
        $ownDepartment = Department::factory()->create(['name' => 'Human Resources']);
        $finance = Department::factory()->create(['name' => 'Finance']);
        [$representative] = $this->employeeIn($ownDepartment, 'HR Representative');
        $this->employeeIn($finance, 'Finance Member');
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();

        $this->actingAs($representative)->getJson(route('hr.team-attendance.status'))
            ->assertOk()
            ->assertJsonPath('department.name', 'Finance')
            ->assertJsonFragment(['employee_name' => 'Finance Member'])
            ->assertJsonMissing(['employee_name' => 'HR Representative']);
    }

    public function test_global_admin_can_poll_any_department_and_employee_cannot_poll_admin_department_url(): void
    {
        $admin = User::factory()->admin()->create();
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        [$financeEmployee] = $this->employeeIn($finance, 'Finance Employee');

        $this->actingAs($admin)
            ->getJson(route('admin.departments.team-attendance.status', $finance))
            ->assertOk()->assertJsonPath('department.name', 'Finance');
        $this->actingAs($admin)
            ->getJson(route('admin.departments.team-attendance.status', $it))
            ->assertOk()->assertJsonPath('department.name', 'IT');

        $this->actingAs($financeEmployee)
            ->getJson(route('admin.departments.team-attendance.status', $it))
            ->assertForbidden();
    }

    /** @return array{User, Employee} */
    private function employeeIn(Department $department, string $name): array
    {
        $user = User::factory()->employee()->create(['name' => $name]);
        $employee = Employee::factory()->for($user)->for($department)->create();

        return [$user, $employee];
    }
}
