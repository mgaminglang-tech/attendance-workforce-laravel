<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAttendanceIndexRequest;
use App\Models\AttendanceSession;
use App\Models\Department;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(AdminAttendanceIndexRequest $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $departmentId = $request->integer('department');
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();
        $state = $request->string('state')->toString();

        $attendanceSessions = AttendanceSession::query()
            ->with(['employee.user', 'employee.department'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('employee', function ($query) use ($search): void {
                    $query->where('employee_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($departmentId > 0, fn ($query) => $query->whereHas(
                'employee',
                fn ($query) => $query->where('department_id', $departmentId),
            ))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('work_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('work_date', '<=', $dateTo))
            ->when($state === 'open', fn ($query) => $query->whereNull('time_out_at'))
            ->when($state === 'completed', fn ($query) => $query->whereNotNull('time_out_at'))
            ->orderByDesc('work_date')
            ->orderByDesc('time_in_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.attendance.index', [
            'attendanceSessions' => $attendanceSessions,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function show(AttendanceSession $attendanceSession): View
    {
        $attendanceSession->load([
            'employee.user',
            'employee.department',
            'adjustments' => fn ($query) => $query
                ->with('administrator')
                ->orderBy('corrected_at')
                ->orderBy('id'),
        ]);

        return view('admin.attendance.show', [
            'attendanceSession' => $attendanceSession,
        ]);
    }
}
