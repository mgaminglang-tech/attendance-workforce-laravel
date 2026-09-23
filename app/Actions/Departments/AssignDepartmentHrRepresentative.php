<?php

namespace App\Actions\Departments;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignDepartmentHrRepresentative
{
    /** @throws ValidationException */
    public function handle(User $administrator, Department $department, User $representative): DepartmentHrAssignment
    {
        Gate::forUser($administrator)->authorize('manage-workforce');

        return DB::transaction(function () use ($administrator, $department, $representative): DepartmentHrAssignment {
            $lockedRepresentative = User::query()->lockForUpdate()->findOrFail($representative->getKey());

            $employee = $lockedRepresentative->employee()->lockForUpdate()->first();

            if (
                ! $lockedRepresentative->hasRole(UserRole::Employee)
                || $lockedRepresentative->account_status !== AccountStatus::Active
                || $employee?->employment_status !== EmploymentStatus::Active
            ) {
                throw ValidationException::withMessages([
                    'user_id' => 'Select an active employee account as the HR Representative.',
                ]);
            }

            $lockedDepartment = Department::query()->lockForUpdate()->findOrFail($department->getKey());
            $existingAssignment = DepartmentHrAssignment::query()
                ->whereBelongsTo($lockedDepartment)
                ->lockForUpdate()
                ->first();

            if ($existingAssignment?->user_id === $lockedRepresentative->getKey()) {
                return $existingAssignment;
            }

            $representativeAlreadyAssigned = DepartmentHrAssignment::query()
                ->whereBelongsTo($lockedRepresentative, 'user')
                ->where('department_id', '!=', $lockedDepartment->getKey())
                ->exists();

            if ($representativeAlreadyAssigned) {
                throw ValidationException::withMessages([
                    'user_id' => 'This employee is already the HR Representative for another department.',
                ]);
            }

            $existingAssignment?->delete();

            $assignment = new DepartmentHrAssignment;
            $assignment->forceFill([
                'department_id' => $lockedDepartment->getKey(),
                'user_id' => $lockedRepresentative->getKey(),
                'assigned_by_user_id' => $administrator->getKey(),
                'assigned_at' => now(config('app.timezone')),
            ])->save();

            return $assignment;
        }, attempts: 3);
    }
}
