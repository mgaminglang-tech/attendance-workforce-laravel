<?php

namespace App\Actions\Attendance;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Exceptions\AttendanceActionException;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TimeInEmployee
{
    /** @throws AttendanceActionException */
    public function handle(User $user): AttendanceSession
    {
        return DB::transaction(function () use ($user): AttendanceSession {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (
                ! $lockedUser->hasRole(UserRole::Employee)
                || $lockedUser->account_status !== AccountStatus::Active
            ) {
                throw AttendanceActionException::accountUnavailable();
            }

            $employee = Employee::query()
                ->whereBelongsTo($lockedUser)
                ->lockForUpdate()
                ->first();

            if ($employee === null) {
                throw AttendanceActionException::employeeProfileMissing();
            }

            if ($employee->employment_status !== EmploymentStatus::Active) {
                throw AttendanceActionException::employmentUnavailable();
            }

            if ($employee->attendanceSessions()->whereNull('time_out_at')->exists()) {
                throw AttendanceActionException::alreadyTimedIn();
            }

            $timeInAt = now(config('app.timezone'));
            $workDate = $timeInAt->toDateString();

            if ($employee->attendanceSessions()->whereDate('work_date', $workDate)->exists()) {
                throw AttendanceActionException::workDateCompleted();
            }

            return $employee->attendanceSessions()->create([
                'work_date' => $workDate,
                'time_in_at' => $timeInAt,
                'time_out_at' => null,
            ]);
        }, attempts: 3);
    }
}
