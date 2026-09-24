<?php

namespace App\Policies;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewReports(User $user, Department $department): bool
    {
        if ($user->account_status !== AccountStatus::Active) {
            return false;
        }

        if ($user->hasRole(UserRole::Admin)) {
            return true;
        }

        return $user->hasRole(UserRole::Employee)
            && $user->employee()
                ->where('employment_status', EmploymentStatus::Active->value)
                ->exists()
            && $user->hrDepartmentAssignment()
                ->whereBelongsTo($department)
                ->exists();
    }

    public function viewTeamAttendance(User $user, Department $department): bool
    {
        if ($user->account_status !== AccountStatus::Active) {
            return false;
        }

        if ($user->hasRole(UserRole::Admin)) {
            return true;
        }

        if (! $user->hasRole(UserRole::Employee)) {
            return false;
        }

        $employee = $user->employee()
            ->where('employment_status', EmploymentStatus::Active->value)
            ->first(['department_id']);

        if ($employee === null) {
            return false;
        }

        return $employee->department_id === $department->getKey()
            || $user->hrDepartmentAssignment()
                ->whereBelongsTo($department)
                ->exists();
    }
}
