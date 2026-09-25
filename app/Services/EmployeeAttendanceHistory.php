<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EmployeeAttendanceHistory
{
    /**
     * @return LengthAwarePaginator<int, array{type: 'attendance'|'leave', date: CarbonInterface, time_in_at: CarbonInterface|null, time_out_at: CarbonInterface|null, work_arrangement: string|null, worked_minutes: int|null, status: string}>
     */
    public function for(Employee $employee): LengthAwarePaginator
    {
        $attendanceRecords = DB::table('attendance_sessions')
            ->selectRaw("'attendance' as record_type, id as record_id, work_date as record_date")
            ->where('employee_id', $employee->getKey());

        $leaveRecords = DB::table('employee_leave_days')
            ->selectRaw("'leave' as record_type, id as record_id, leave_date as record_date")
            ->where('employee_id', $employee->getKey())
            ->whereNotExists(function (Builder $query) use ($employee): void {
                $query->selectRaw('1')
                    ->from('attendance_sessions')
                    ->where('employee_id', $employee->getKey())
                    ->whereColumn('attendance_sessions.work_date', 'employee_leave_days.leave_date');
            });

        $records = DB::query()
            ->fromSub($attendanceRecords->unionAll($leaveRecords), 'history_records')
            ->orderByDesc('record_date')
            ->orderBy('record_type')
            ->orderByDesc('record_id')
            ->paginate(15);

        $attendanceSessions = AttendanceSession::query()
            ->whereIn('id', $records->getCollection()->where('record_type', 'attendance')->pluck('record_id'))
            ->get()
            ->keyBy('id');

        $leaveDays = EmployeeLeaveDay::query()
            ->whereIn('id', $records->getCollection()->where('record_type', 'leave')->pluck('record_id'))
            ->get()
            ->keyBy('id');

        $records->setCollection($records->getCollection()->map(function (object $record) use ($attendanceSessions, $leaveDays): array {
            if ($record->record_type === 'leave') {
                $leaveDay = $leaveDays->get($record->record_id);

                return [
                    'type' => 'leave',
                    'date' => $leaveDay->leave_date,
                    'time_in_at' => null,
                    'time_out_at' => null,
                    'work_arrangement' => null,
                    'worked_minutes' => null,
                    'status' => 'On Leave',
                ];
            }

            $attendanceSession = $attendanceSessions->get($record->record_id);
            $workedMinutes = $attendanceSession->workedMinutes();

            return [
                'type' => 'attendance',
                'date' => $attendanceSession->work_date,
                'time_in_at' => $attendanceSession->time_in_at,
                'time_out_at' => $attendanceSession->time_out_at,
                'work_arrangement' => $attendanceSession->work_arrangement?->label(),
                'worked_minutes' => $workedMinutes,
                'status' => $workedMinutes === null ? 'Working' : 'Completed',
            ];
        }));

        return $records;
    }
}
