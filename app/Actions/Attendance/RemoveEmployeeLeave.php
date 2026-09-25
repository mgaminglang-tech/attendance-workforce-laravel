<?php

namespace App\Actions\Attendance;

use App\Exceptions\LeaveActionException;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use Illuminate\Support\Facades\DB;

class RemoveEmployeeLeave
{
    /** @throws LeaveActionException */
    public function handle(Employee $employee, EmployeeLeaveDay $leaveDay): void
    {
        DB::transaction(function () use ($employee, $leaveDay): void {
            $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->getKey());
            $lockedLeaveDay = $lockedEmployee->leaveDays()->lockForUpdate()->findOrFail($leaveDay->getKey());

            if ($lockedLeaveDay->leave_date->isBefore(now(config('app.timezone'))->startOfDay())) {
                throw LeaveActionException::pastLeaveCannotBeRemoved();
            }

            $lockedLeaveDay->delete();
        }, attempts: 3);
    }
}
