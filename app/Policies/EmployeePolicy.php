<?php

namespace App\Policies;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewDepartmentDtr(User $user, Employee $employee): bool
    {
        if ($user->account_status !== AccountStatus::Active) {
            return false;
        }

        if ($user->hasRole(UserRole::Admin)) {
            return true;
        }

        if (! $user->hasRole(UserRole::Employee) || $employee->department_id === null) {
            return false;
        }

        return $user->hrDepartmentAssignment()
            ->where('department_id', $employee->department_id)
            ->exists();
    }
}
