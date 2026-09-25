<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\EmployeeLeaveDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AttendanceReportQuery
{
    public function __construct(
        private CalculateNetAttendanceMinutes $calculateNetAttendanceMinutes,
    ) {}

    /**
     * @return array{
     *     sessions: LengthAwarePaginator<int, array<string, mixed>>,
     *     summary: array{records: int, unique_employees: int, completed: int, open: int, on_leave: int, total_net_hours: string}
     * }
     */
    public function handle(AttendanceReportFilters $filters): array
    {
        $attendanceQuery = $this->filteredAttendanceQuery($filters);
        $leaveQuery = $this->filteredLeaveQuery($filters);
        $summary = $this->summary($attendanceQuery, $leaveQuery);

        $attendanceRecords = (clone $attendanceQuery)
            ->selectRaw("'attendance' as record_type, id as record_id, work_date as record_date, employee_id");
        $leaveRecords = (clone $leaveQuery)
            ->selectRaw("'leave' as record_type, id as record_id, leave_date as record_date, employee_id");

        $sessions = DB::query()
            ->fromSub($attendanceRecords->unionAll($leaveRecords), 'report_records')
            ->join('employees', 'employees.id', '=', 'report_records.employee_id')
            ->select('report_records.*')
            ->orderByDesc('record_date')
            ->orderBy('employees.employee_number')
            ->orderByDesc('record_id')
            ->paginate(25)
            ->withQueryString();

        $attendanceSessions = AttendanceSession::query()
            ->with([
                'employee:id,user_id,department_id,employee_number',
                'employee.user:id,name',
                'employee.department:id,name',
            ])
            ->whereIn('id', $sessions->getCollection()->where('record_type', 'attendance')->pluck('record_id'))
            ->get()
            ->keyBy('id');
        $leaveDays = EmployeeLeaveDay::query()
            ->with([
                'employee:id,user_id,department_id,employee_number',
                'employee.user:id,name',
                'employee.department:id,name',
            ])
            ->whereIn('id', $sessions->getCollection()->where('record_type', 'leave')->pluck('record_id'))
            ->get()
            ->keyBy('id');
        $timezone = config('app.timezone');

        $sessions->setCollection($sessions->getCollection()->map(function (object $record) use ($attendanceSessions, $leaveDays, $timezone): array {
            if ($record->record_type === 'leave') {
                $leaveDay = $leaveDays->get($record->record_id);

                return [
                    'id' => $leaveDay->getKey(),
                    'employee_id' => $leaveDay->employee->getKey(),
                    'employee_name' => $leaveDay->employee->user->name,
                    'employee_number' => $leaveDay->employee->employee_number,
                    'department_name' => $leaveDay->employee->department?->name ?? 'Unassigned',
                    'work_date' => $leaveDay->leave_date,
                    'time_in' => '',
                    'time_out' => '',
                    'work_arrangement' => null,
                    'net_hours' => '',
                    'status' => 'On Leave',
                ];
            }

            $attendanceSession = $attendanceSessions->get($record->record_id);
            $netMinutes = $this->calculateNetAttendanceMinutes->handle($attendanceSession);

            return [
                'id' => $attendanceSession->getKey(),
                'employee_id' => $attendanceSession->employee->getKey(),
                'employee_name' => $attendanceSession->employee->user->name,
                'employee_number' => $attendanceSession->employee->employee_number,
                'department_name' => $attendanceSession->employee->department?->name ?? 'Unassigned',
                'work_date' => $attendanceSession->work_date,
                'time_in' => $attendanceSession->time_in_at->setTimezone($timezone)->format('g:i A'),
                'time_out' => $attendanceSession->time_out_at?->setTimezone($timezone)->format('g:i A') ?? '',
                'work_arrangement' => $attendanceSession->work_arrangement?->label() ?? 'Not recorded',
                'net_hours' => $netMinutes === null ? '' : number_format($netMinutes / 60, 2, '.', ''),
                'status' => $attendanceSession->time_out_at === null ? 'Open / Working' : 'Completed',
            ];
        }));

        return compact('sessions', 'summary');
    }

    /** @return Builder<AttendanceSession> */
    private function filteredAttendanceQuery(AttendanceReportFilters $filters): Builder
    {
        return AttendanceSession::query()
            ->whereDate('work_date', '>=', $filters->dateFrom->toDateString())
            ->whereDate('work_date', '<=', $filters->dateTo->toDateString())
            ->when($filters->departmentId !== null, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $query) => $query->where('department_id', $filters->departmentId),
            ))
            ->when($filters->employeeId !== null, fn (Builder $query) => $query->where('employee_id', $filters->employeeId))
            ->when($filters->workArrangement === 'not_recorded', fn (Builder $query) => $query->whereNull('work_arrangement'))
            ->when(
                $filters->workArrangement !== null && $filters->workArrangement !== 'not_recorded',
                fn (Builder $query) => $query->where('work_arrangement', $filters->workArrangement),
            )
            ->when($filters->state === 'open', fn (Builder $query) => $query->whereNull('time_out_at'))
            ->when($filters->state === 'completed', fn (Builder $query) => $query->whereNotNull('time_out_at'))
            ->when($filters->state === 'on_leave', fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    /** @return Builder<EmployeeLeaveDay> */
    private function filteredLeaveQuery(AttendanceReportFilters $filters): Builder
    {
        return EmployeeLeaveDay::query()
            ->whereDate('leave_date', '>=', $filters->dateFrom->toDateString())
            ->whereDate('leave_date', '<=', $filters->dateTo->toDateString())
            ->when($filters->departmentId !== null, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $query) => $query->where('department_id', $filters->departmentId),
            ))
            ->when($filters->employeeId !== null, fn (Builder $query) => $query->where('employee_id', $filters->employeeId))
            ->when($filters->workArrangement !== null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(in_array($filters->state, ['open', 'completed'], true), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('attendance_sessions')
                    ->whereColumn('attendance_sessions.employee_id', 'employee_leave_days.employee_id')
                    ->whereColumn('attendance_sessions.work_date', 'employee_leave_days.leave_date');
            });
    }

    /**
     * @param  Builder<AttendanceSession>  $attendanceQuery
     * @param  Builder<EmployeeLeaveDay>  $leaveQuery
     * @return array{records: int, unique_employees: int, completed: int, open: int, on_leave: int, total_net_hours: string}
     */
    private function summary(Builder $attendanceQuery, Builder $leaveQuery): array
    {
        $completed = (clone $attendanceQuery)->whereNotNull('time_out_at')->count();
        $open = (clone $attendanceQuery)->whereNull('time_out_at')->count();
        $onLeave = (clone $leaveQuery)->count();
        $employeeIds = (clone $attendanceQuery)->select('employee_id')
            ->union((clone $leaveQuery)->select('employee_id'));
        $uniqueEmployees = DB::query()
            ->fromSub($employeeIds, 'report_employee_ids')
            ->distinct()
            ->count('employee_id');
        $totalNetMinutes = 0;

        (clone $attendanceQuery)
            ->whereNotNull('time_out_at')
            ->select(['id', 'time_in_at', 'time_out_at'])
            ->chunkById(500, function ($sessions) use (&$totalNetMinutes): void {
                foreach ($sessions as $attendanceSession) {
                    $totalNetMinutes += $this->calculateNetAttendanceMinutes->handle($attendanceSession) ?? 0;
                }
            });

        return [
            'records' => $completed + $open + $onLeave,
            'unique_employees' => $uniqueEmployees,
            'completed' => $completed,
            'open' => $open,
            'on_leave' => $onLeave,
            'total_net_hours' => number_format($totalNetMinutes / 60, 2, '.', ''),
        ];
    }
}
