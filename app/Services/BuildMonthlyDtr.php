<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use Carbon\CarbonImmutable;

class BuildMonthlyDtr
{
    public function __construct(
        private CalculateNetAttendanceMinutes $calculateNetAttendanceMinutes,
    ) {}

    /**
     * @return array{
     *     employee: Employee,
     *     employee_name: string,
     *     employee_number: string,
     *     department_name: ?string,
     *     month: string,
     *     month_label: string,
     *     first_date: CarbonImmutable,
     *     last_date: CarbonImmutable,
     *     first_date_label: string,
     *     last_date_label: string,
     *     rows: list<array{
     *         date: CarbonImmutable,
     *         date_label: string,
     *         time_in: string,
     *         time_out: string,
     *         total_hours: string,
     *         work_arrangement: ?string,
     *         attendance_rendered: string,
     *         remarks: string,
     *         has_attendance: bool,
     *         has_leave: bool,
     *         has_legacy_arrangement: bool
     *     }>
     * }
     */
    public function handle(Employee $employee, CarbonImmutable $selectedMonth): array
    {
        $timezone = config('app.timezone');
        $firstDate = $selectedMonth->setTimezone($timezone)->startOfMonth()->startOfDay();
        $lastDate = $firstDate->endOfMonth()->startOfDay();

        $employee->loadMissing([
            'user:id,name',
            'department:id,name',
        ]);

        $sessions = AttendanceSession::query()
            ->select([
                'id',
                'employee_id',
                'work_date',
                'time_in_at',
                'time_out_at',
                'work_arrangement',
            ])
            ->whereBelongsTo($employee)
            ->whereBetween('work_date', [$firstDate->toDateString(), $lastDate->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn (AttendanceSession $session): string => $session->work_date->toDateString());
        $leaveDays = EmployeeLeaveDay::query()
            ->select(['id', 'employee_id', 'leave_date'])
            ->whereBelongsTo($employee)
            ->whereBetween('leave_date', [$firstDate->toDateString(), $lastDate->toDateString()])
            ->orderBy('leave_date')
            ->get()
            ->keyBy(fn (EmployeeLeaveDay $leaveDay): string => $leaveDay->leave_date->toDateString());

        $rows = [];

        for ($date = $firstDate; $date->lessThanOrEqualTo($lastDate); $date = $date->addDay()) {
            /** @var AttendanceSession|null $session */
            $session = $sessions->get($date->toDateString());
            $isOnLeave = $session === null && $leaveDays->has($date->toDateString());
            $netMinutes = $session === null
                ? null
                : $this->calculateNetAttendanceMinutes->handle($session);

            $rows[] = [
                'date' => $date,
                'date_label' => $date->format('M d, Y'),
                'time_in' => $session?->time_in_at->setTimezone($timezone)->format('g:i A') ?? '',
                'time_out' => $session?->time_out_at?->setTimezone($timezone)->format('g:i A') ?? '',
                'total_hours' => $netMinutes === null
                    ? ''
                    : number_format($netMinutes / 60, 2, '.', ''),
                'work_arrangement' => $session?->work_arrangement?->label(),
                'attendance_rendered' => $isOnLeave ? 'ON LEAVE' : ($session?->work_arrangement?->dtrLabel() ?? ''),
                'remarks' => '',
                'has_attendance' => $session !== null,
                'has_leave' => $isOnLeave,
                'has_legacy_arrangement' => $session !== null && $session->work_arrangement === null,
            ];
        }

        return [
            'employee' => $employee,
            'employee_name' => $employee->user->name,
            'employee_number' => $employee->employee_number,
            'department_name' => $employee->department?->name,
            'month' => $firstDate->format('Y-m'),
            'month_label' => $firstDate->format('F Y'),
            'first_date' => $firstDate,
            'last_date' => $lastDate,
            'first_date_label' => mb_strtoupper($firstDate->format('F d, Y')),
            'last_date_label' => mb_strtoupper($lastDate->format('F d, Y')),
            'rows' => $rows,
        ];
    }
}
