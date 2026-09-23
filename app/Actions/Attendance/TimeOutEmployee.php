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

class TimeOutEmployee
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

            $attendanceSession = AttendanceSession::query()
                ->whereBelongsTo($employee)
                ->whereNull('time_out_at')
                ->lockForUpdate()
                ->first();

            if ($attendanceSession === null) {
                throw AttendanceActionException::noOpenSession();
            }

            $timeOutAt = now(config('app.timezone'));

            if ($timeOutAt->lt($attendanceSession->time_in_at)) {
                throw AttendanceActionException::timeOutPrecedesTimeIn();
            }

            $attendanceSession->forceFill(['time_out_at' => $timeOutAt])->save();

            return $attendanceSession;
        }, attempts: 3);
    }
}
