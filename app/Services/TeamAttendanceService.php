<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Models\AttendanceSession;
use App\Models\Department;
use Carbon\CarbonImmutable;

class TeamAttendanceService
{
    /**
     * @return array{
     *     department: array{id: int, name: string},
     *     summary: array{total: int, working: int, completed: int, not_clocked_in: int},
     *     members: list<array{employee_name: string, employee_number: string, status: string, time_in: ?string, time_out: ?string, work_date: ?string}>,
     *     last_updated: string
     * }
     */
    public function forDepartment(Department $department): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $workDate = $now->toDateString();

        $employees = $department->employees()
            ->select(['id', 'user_id', 'employee_number', 'department_id'])
            ->where('employment_status', EmploymentStatus::Active->value)
            ->whereHas('user', fn ($query) => $query->where('account_status', AccountStatus::Active->value))
            ->with([
                'user:id,name',
                'attendanceSessions' => fn ($query) => $query
                    ->select(['id', 'employee_id', 'work_date', 'time_in_at', 'time_out_at'])
                    ->where(function ($query) use ($workDate): void {
                        $query->whereNull('time_out_at')
                            ->orWhereDate('work_date', $workDate);
                    })
                    ->orderByDesc('time_in_at'),
            ])
            ->orderBy('employee_number')
            ->get();

        $members = $employees->map(function ($employee) use ($workDate): array {
            $openSession = $employee->attendanceSessions->first(
                fn (AttendanceSession $session): bool => $session->time_out_at === null,
            );
            $currentSession = $openSession ?? $employee->attendanceSessions->first(
                fn (AttendanceSession $session): bool => $session->work_date->toDateString() === $workDate
                    && $session->time_out_at !== null,
            );
            $status = match (true) {
                $openSession !== null => 'Working',
                $currentSession !== null => 'Completed',
                default => 'Not clocked in',
            };

            return [
                'employee_name' => $employee->user->name,
                'employee_number' => $employee->employee_number,
                'status' => $status,
                'time_in' => $currentSession?->time_in_at->format('M j, Y g:i:s A'),
                'time_out' => $currentSession?->time_out_at?->format('M j, Y g:i:s A'),
                'work_date' => $currentSession?->work_date->format('M j, Y'),
            ];
        })->values();

        return [
            'department' => ['id' => $department->getKey(), 'name' => $department->name],
            'summary' => [
                'total' => $members->count(),
                'working' => $members->where('status', 'Working')->count(),
                'completed' => $members->where('status', 'Completed')->count(),
                'not_clocked_in' => $members->where('status', 'Not clocked in')->count(),
            ],
            'members' => $members->all(),
            'last_updated' => $now->format('M j, Y g:i:s A'),
        ];
    }
}
