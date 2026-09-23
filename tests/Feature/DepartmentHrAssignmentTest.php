<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DepartmentHrAssignmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_global_admin_can_assign_an_active_employee_as_hr_representative(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 14:30:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        [$representative] = $this->eligibleRepresentative();
        $department = Department::factory()->create();

        $this->actingAs($admin)->put(route('admin.departments.hr-representative.update', $department), [
            'user_id' => $representative->id,
        ])->assertRedirect(route('admin.departments.edit', $department))
            ->assertSessionHas('status', 'HR Representative assignment updated.');

        $assignment = DepartmentHrAssignment::sole();
        $this->assertSame($department->id, $assignment->department_id);
        $this->assertSame($representative->id, $assignment->user_id);
        $this->assertSame($admin->id, $assignment->assigned_by_user_id);
        $this->assertSame('2026-09-23 14:30:00', $assignment->assigned_at->format('Y-m-d H:i:s'));
    }

    public function test_employee_and_hr_representative_cannot_change_assignments_or_assign_themselves(): void
    {
        [$employee] = $this->eligibleRepresentative();
        $department = Department::factory()->create();
        DepartmentHrAssignment::factory()->for($department)->for($employee)->create();

        $this->actingAs($employee)->put(route('admin.departments.hr-representative.update', $department), [
            'user_id' => $employee->id,
        ])->assertForbidden();
        $this->actingAs($employee)
            ->delete(route('admin.departments.hr-representative.destroy', $department))
            ->assertForbidden();

        $this->assertSame($employee->id, $department->hrAssignment()->sole()->user_id);
    }

    public function test_pending_disabled_or_non_employee_users_cannot_be_assigned(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $pending = User::factory()->employee()->pending()->create();
        Employee::factory()->for($pending)->create();
        $disabled = User::factory()->employee()->disabled()->create();
        Employee::factory()->for($disabled)->create();
        $inactive = User::factory()->employee()->create();
        Employee::factory()->for($inactive)->create(['employment_status' => EmploymentStatus::Inactive]);

        foreach ([$pending, $disabled, $inactive, $admin] as $ineligibleUser) {
            $this->actingAs($admin)->put(route('admin.departments.hr-representative.update', $department), [
                'user_id' => $ineligibleUser->id,
            ])->assertSessionHasErrors([
                'user_id' => 'Select an active employee account as the HR Representative.',
            ]);
        }

        $this->assertDatabaseCount('department_hr_assignments', 0);
    }

    public function test_global_admin_can_replace_and_remove_hr_representative_safely(): void
    {
        $admin = User::factory()->admin()->create();
        [$first] = $this->eligibleRepresentative();
        [$replacement] = $this->eligibleRepresentative();
        $department = Department::factory()->create();
        DepartmentHrAssignment::factory()->for($department)->for($first)->create();

        $this->actingAs($admin)->put(route('admin.departments.hr-representative.update', $department), [
            'user_id' => $replacement->id,
        ])->assertRedirect(route('admin.departments.edit', $department));

        $this->assertDatabaseCount('department_hr_assignments', 1);
        $this->assertSame($replacement->id, $department->hrAssignment()->sole()->user_id);

        $this->actingAs($admin)
            ->delete(route('admin.departments.hr-representative.destroy', $department))
            ->assertRedirect(route('admin.departments.edit', $department))
            ->assertSessionHas('status', 'HR Representative assignment removed.');
        $this->assertDatabaseCount('department_hr_assignments', 0);
    }

    public function test_representative_cannot_be_assigned_to_two_departments(): void
    {
        $admin = User::factory()->admin()->create();
        [$representative] = $this->eligibleRepresentative();
        $finance = Department::factory()->create();
        $operations = Department::factory()->create();
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();

        $this->actingAs($admin)->put(route('admin.departments.hr-representative.update', $operations), [
            'user_id' => $representative->id,
        ])->assertSessionHasErrors([
            'user_id' => 'This employee is already the HR Representative for another department.',
        ]);

        $this->assertDatabaseCount('department_hr_assignments', 1);
    }

    public function test_database_constraints_enforce_unique_department_and_representative(): void
    {
        [$representative] = $this->eligibleRepresentative();
        [$otherRepresentative] = $this->eligibleRepresentative();
        $finance = Department::factory()->create();
        $operations = Department::factory()->create();
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();

        try {
            DepartmentHrAssignment::factory()->for($finance)->for($otherRepresentative)->create();
            $this->fail('A department must not have two HR Representatives.');
        } catch (QueryException) {
            $this->assertDatabaseCount('department_hr_assignments', 1);
        }

        $this->expectException(QueryException::class);
        DepartmentHrAssignment::factory()->for($operations)->for($representative)->create();
    }

    public function test_assignment_foreign_keys_restrict_silent_parent_deletion(): void
    {
        $assignment = DepartmentHrAssignment::factory()->create();

        try {
            $assignment->department->delete();
            $this->fail('An assigned department must not be deleted.');
        } catch (QueryException) {
            $this->assertModelExists($assignment);
        }

        $this->expectException(QueryException::class);
        $assignment->user->delete();
    }

    /** @return array{User, Employee} */
    private function eligibleRepresentative(): array
    {
        $user = User::factory()->employee()->create(['account_status' => AccountStatus::Active]);
        $employee = Employee::factory()->for($user)->create();

        return [$user, $employee];
    }
}
