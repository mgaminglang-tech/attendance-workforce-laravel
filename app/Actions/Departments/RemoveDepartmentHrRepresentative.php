<?php

namespace App\Actions\Departments;

use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveDepartmentHrRepresentative
{
    public function handle(User $administrator, Department $department): void
    {
        Gate::forUser($administrator)->authorize('manage-workforce');

        DB::transaction(function () use ($department): void {
            $lockedDepartment = Department::query()->lockForUpdate()->findOrFail($department->getKey());

            DepartmentHrAssignment::query()
                ->whereBelongsTo($lockedDepartment)
                ->lockForUpdate()
                ->first()
                ?->delete();
        }, attempts: 3);
    }
}
