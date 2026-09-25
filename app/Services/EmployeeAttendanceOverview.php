<?php

namespace App\Services;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use Illuminate\Support\Collection;

class EmployeeAttendanceOverview
{
    public function __construct(private CalculateNetAttendanceMinutes $calculateNetAttendanceMinutes) {}

    /** @return array{currentSession: AttendanceSession|null, todayLeave: EmployeeLeaveDay|null, upcomingLeaveDays: Collection<int, EmployeeLeaveDay>, netWorkedMinutes: int|null, workDate: string, workArrangements: array<int, WorkArrangement>} */
    public function for(Employee $employee): array
    {
        $workDate = now(config('app.timezone'))->toDateString();

        $currentSession = $employee->attendanceSessions()
            ->whereNull('time_out_at')
            ->latest('time_in_at')
            ->first();

        $currentSession ??= $employee->attendanceSessions()
            ->whereDate('work_date', $workDate)
            ->latest('time_in_at')
            ->first();

        $upcomingLeaveDays = $employee->leaveDays()
            ->whereDate('leave_date', '>=', $workDate)
            ->orderBy('leave_date')
            ->get();

        return [
            'currentSession' => $currentSession,
            'todayLeave' => $upcomingLeaveDays->first(
                fn (EmployeeLeaveDay $leaveDay): bool => $leaveDay->leave_date->toDateString() === $workDate,
            ),
            'upcomingLeaveDays' => $upcomingLeaveDays,
            'netWorkedMinutes' => $currentSession === null
                ? null
                : $this->calculateNetAttendanceMinutes->handle($currentSession),
            'workDate' => $workDate,
            'workArrangements' => WorkArrangement::cases(),
        ];
    }
}
