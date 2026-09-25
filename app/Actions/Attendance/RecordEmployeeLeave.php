<?php

namespace App\Actions\Attendance;

use App\Enums\EmploymentStatus;
use App\Exceptions\LeaveActionException;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecordEmployeeLeave
{
    /**
     * @return Collection<int, EmployeeLeaveDay>
     *
     * @throws LeaveActionException
     */
    public function handle(Employee $employee, CarbonImmutable $fromDate, CarbonImmutable $toDate): Collection
    {
        try {
            return DB::transaction(function () use ($employee, $fromDate, $toDate): Collection {
                $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->getKey());

                if ($lockedEmployee->employment_status !== EmploymentStatus::Active) {
                    throw LeaveActionException::employmentUnavailable();
                }

                $leaveDates = collect();

                for ($date = $fromDate->startOfDay(); $date->lte($toDate); $date = $date->addDay()) {
                    $leaveDates->push($date->toDateString());
                }

                if ($lockedEmployee->attendanceSessions()
                    ->whereDate('work_date', '>=', $fromDate->toDateString())
                    ->whereDate('work_date', '<=', $toDate->toDateString())
                    ->exists()) {
                    throw LeaveActionException::attendanceConflict();
                }

                if ($lockedEmployee->leaveDays()
                    ->whereDate('leave_date', '>=', $fromDate->toDateString())
                    ->whereDate('leave_date', '<=', $toDate->toDateString())
                    ->exists()) {
                    throw LeaveActionException::duplicateLeave();
                }

                $leaveDays = $leaveDates->map(function (string $leaveDate): EmployeeLeaveDay {
                    return (new EmployeeLeaveDay)->forceFill(['leave_date' => $leaveDate]);
                });

                $lockedEmployee->leaveDays()->saveMany($leaveDays);

                return $leaveDays;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw LeaveActionException::duplicateLeave();
            }

            throw $exception;
        }
    }
}
