<?php

namespace App\Services;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use App\Models\Employee;

class EmployeeAttendanceOverview
{
    public function __construct(private CalculateNetAttendanceMinutes $calculateNetAttendanceMinutes) {}

    /** @return array{currentSession: AttendanceSession|null, netWorkedMinutes: int|null, workDate: string, workArrangements: array<int, WorkArrangement>} */
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

        return [
            'currentSession' => $currentSession,
            'netWorkedMinutes' => $currentSession === null
                ? null
                : $this->calculateNetAttendanceMinutes->handle($currentSession),
            'workDate' => $workDate,
            'workArrangements' => WorkArrangement::cases(),
        ];
    }
}
