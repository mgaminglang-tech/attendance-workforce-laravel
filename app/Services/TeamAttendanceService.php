<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class TeamAttendanceService
{
    public function __construct(private CalculateNetAttendanceMinutes $calculateNetAttendanceMinutes) {}

    /**
     * @return array{
     *     department: array{id: int, name: string},
     *     summary: array{total: int, working: int, completed: int, on_leave: int, not_clocked_in: int},
     *     activity: list<array{employee_name: string, employee_initials: string, is_hr_representative: bool, event: string, event_time: ?string, occurred_at: ?string, date_label: string, work_arrangement: ?string, net_hours: ?string}>,
     *     last_updated: string
     * }
     */
    public function forDepartment(Department $department): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $workDate = $now->toDateString();
        $department->loadMissing('hrAssignment');

        $employees = $department->employees()
            ->select(['id', 'user_id', 'department_id'])
            ->where('employment_status', EmploymentStatus::Active->value)
            ->whereHas('user', fn ($query) => $query->where('account_status', AccountStatus::Active->value))
            ->with([
                'user:id,name',
                'attendanceSessions' => fn ($query) => $query
                    ->select(['id', 'employee_id', 'work_date', 'time_in_at', 'time_out_at', 'work_arrangement'])
                    ->where(function ($query) use ($workDate): void {
                        $query->whereNull('time_out_at')
                            ->orWhereDate('work_date', $workDate);
                    })
                    ->orderByDesc('time_in_at'),
                'leaveDays' => fn ($query) => $query
                    ->select(['id', 'employee_id', 'leave_date'])
                    ->whereDate('leave_date', $workDate),
            ])
            ->orderBy('employee_number')
            ->get();

        $employeeStates = $employees->map(function (Employee $employee) use ($workDate): array {
            $openSession = $this->openSession($employee);
            $currentSession = $openSession ?? $this->completedSessionForWorkDate($employee, $workDate);
            $isOnLeave = $currentSession === null && $employee->leaveDays->isNotEmpty();
            $status = match (true) {
                $openSession !== null => 'Working',
                $currentSession !== null => 'Completed',
                $isOnLeave => 'On Leave',
                default => 'Not clocked in',
            };

            return compact('employee', 'currentSession', 'isOnLeave', 'status');
        });

        $activityEvents = $employeeStates
            ->flatMap(fn (array $state): array => $this->activityEvents(
                $state['employee'],
                $state['currentSession'],
                $state['isOnLeave'],
                $department->hrAssignment?->user_id,
                $now,
            ))
            ->reject(fn (array $event): bool => $event['is_hr_representative']);
        $activity = $activityEvents
            ->where('date_label', 'Today')
            ->sortByDesc('occurred_at')
            ->concat(
                $activityEvents
                    ->where('date_label', '!=', 'Today')
                    ->sortByDesc('occurred_at'),
            )
            ->values();

        return [
            'department' => ['id' => $department->getKey(), 'name' => $department->name],
            'summary' => [
                'total' => $employeeStates->count(),
                'working' => $employeeStates->where('status', 'Working')->count(),
                'completed' => $employeeStates->where('status', 'Completed')->count(),
                'on_leave' => $employeeStates->where('status', 'On Leave')->count(),
                'not_clocked_in' => $employeeStates->where('status', 'Not clocked in')->count(),
            ],
            'activity' => $activity->all(),
            'last_updated' => $now->format('M j, Y g:i:s A'),
        ];
    }

    private function openSession(Employee $employee): ?AttendanceSession
    {
        return $employee->attendanceSessions->first(
            fn (AttendanceSession $session): bool => $session->time_out_at === null,
        );
    }

    private function completedSessionForWorkDate(Employee $employee, string $workDate): ?AttendanceSession
    {
        return $employee->attendanceSessions->first(
            fn (AttendanceSession $session): bool => $session->work_date->toDateString() === $workDate
                && $session->time_out_at !== null,
        );
    }

    /**
     * @return list<array{employee_name: string, employee_initials: string, is_hr_representative: bool, event: string, event_time: ?string, occurred_at: ?string, date_label: string, work_arrangement: ?string, net_hours: ?string}>
     */
    private function activityEvents(
        Employee $employee,
        ?AttendanceSession $session,
        bool $isOnLeave,
        ?int $hrRepresentativeUserId,
        CarbonImmutable $now,
    ): array {
        $employeeName = $employee->user->name;
        $shared = [
            'employee_name' => $employeeName,
            'employee_initials' => $this->initials($employeeName),
            'is_hr_representative' => $employee->user_id === $hrRepresentativeUserId,
        ];

        if ($session === null) {
            return [[
                ...$shared,
                'event' => $isOnLeave ? 'On Leave' : 'Not Clocked In',
                'event_time' => null,
                'occurred_at' => null,
                'date_label' => 'Today',
                'work_arrangement' => null,
                'net_hours' => null,
            ]];
        }

        $shared['work_arrangement'] = $session->work_arrangement?->label() ?? 'Not recorded';
        $events = [
            [...$shared, ...$this->eventDetails('Timed in', $session->time_in_at, $now), 'net_hours' => null],
        ];

        if ($session->time_out_at !== null) {
            $netMinutes = $this->calculateNetAttendanceMinutes->handle($session);
            $events[] = [
                ...$shared,
                ...$this->eventDetails('Timed out', $session->time_out_at, $now),
                'net_hours' => $netMinutes === null
                    ? null
                    : number_format($netMinutes / 60, 2, '.', '').' hrs',
            ];
        }

        return $events;
    }

    /** @return array{event: string, event_time: string, occurred_at: string, date_label: string} */
    private function eventDetails(string $event, CarbonImmutable $occurredAt, CarbonImmutable $now): array
    {
        return [
            'event' => $event,
            'event_time' => $occurredAt->format('g:i A'),
            'occurred_at' => $occurredAt->toIso8601String(),
            'date_label' => $occurredAt->isSameDay($now) ? 'Today' : $occurredAt->format('M j, Y'),
        ];
    }

    private function initials(string $name): string
    {
        return Str::of($name)
            ->squish()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
