<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AttendanceReportQuery
{
    public function __construct(
        private CalculateNetAttendanceMinutes $calculateNetAttendanceMinutes,
    ) {}

    /**
     * @return array{
     *     sessions: LengthAwarePaginator<int, array<string, mixed>>,
     *     summary: array{
     *         records: int,
     *         unique_employees: int,
     *         completed: int,
     *         open: int,
     *         total_net_hours: string
     *     }
     * }
     */
    public function handle(AttendanceReportFilters $filters): array
    {
        $query = $this->filteredQuery($filters);
        $summary = $this->summary($query);
        $timezone = config('app.timezone');

        $sessions = (clone $query)
            ->with([
                'employee:id,user_id,department_id,employee_number',
                'employee.user:id,name',
                'employee.department:id,name',
            ])
            ->orderByDesc('work_date')
            ->orderBy(
                Employee::query()
                    ->select('employee_number')
                    ->whereColumn('employees.id', 'attendance_sessions.employee_id'),
            )
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (AttendanceSession $attendanceSession) use ($timezone): array {
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
                    'net_hours' => $netMinutes === null
                        ? ''
                        : number_format($netMinutes / 60, 2, '.', ''),
                    'status' => $attendanceSession->time_out_at === null ? 'Open / Working' : 'Completed',
                ];
            });

        return [
            'sessions' => $sessions,
            'summary' => $summary,
        ];
    }

    /** @return Builder<AttendanceSession> */
    private function filteredQuery(AttendanceReportFilters $filters): Builder
    {
        return AttendanceSession::query()
            ->whereDate('work_date', '>=', $filters->dateFrom->toDateString())
            ->whereDate('work_date', '<=', $filters->dateTo->toDateString())
            ->when($filters->departmentId !== null, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $query) => $query->where('department_id', $filters->departmentId),
            ))
            ->when(
                $filters->employeeId !== null,
                fn (Builder $query) => $query->where('employee_id', $filters->employeeId),
            )
            ->when(
                $filters->workArrangement === 'not_recorded',
                fn (Builder $query) => $query->whereNull('work_arrangement'),
            )
            ->when(
                $filters->workArrangement !== null && $filters->workArrangement !== 'not_recorded',
                fn (Builder $query) => $query->where('work_arrangement', $filters->workArrangement),
            )
            ->when($filters->state === 'open', fn (Builder $query) => $query->whereNull('time_out_at'))
            ->when($filters->state === 'completed', fn (Builder $query) => $query->whereNotNull('time_out_at'));
    }

    /**
     * @param  Builder<AttendanceSession>  $query
     * @return array{records: int, unique_employees: int, completed: int, open: int, total_net_hours: string}
     */
    private function summary(Builder $query): array
    {
        $aggregate = (clone $query)
            ->toBase()
            ->selectRaw('COUNT(*) as records_count')
            ->selectRaw('COUNT(DISTINCT employee_id) as unique_employees_count')
            ->selectRaw('SUM(CASE WHEN time_out_at IS NOT NULL THEN 1 ELSE 0 END) as completed_count')
            ->selectRaw('SUM(CASE WHEN time_out_at IS NULL THEN 1 ELSE 0 END) as open_count')
            ->first();

        $totalNetMinutes = 0;

        (clone $query)
            ->whereNotNull('time_out_at')
            ->select(['id', 'time_in_at', 'time_out_at'])
            ->chunkById(500, function ($sessions) use (&$totalNetMinutes): void {
                foreach ($sessions as $attendanceSession) {
                    $totalNetMinutes += $this->calculateNetAttendanceMinutes->handle($attendanceSession) ?? 0;
                }
            });

        return [
            'records' => (int) ($aggregate->records_count ?? 0),
            'unique_employees' => (int) ($aggregate->unique_employees_count ?? 0),
            'completed' => (int) ($aggregate->completed_count ?? 0),
            'open' => (int) ($aggregate->open_count ?? 0),
            'total_net_hours' => number_format($totalNetMinutes / 60, 2, '.', ''),
        ];
    }
}
