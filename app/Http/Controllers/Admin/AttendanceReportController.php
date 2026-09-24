<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceReportRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceReportQuery;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    public function index(
        AttendanceReportRequest $request,
        AttendanceReportQuery $attendanceReportQuery,
    ): View {
        $filters = $request->filters();

        return view('reports.attendance', [
            ...$attendanceReportQuery->handle($filters),
            'filters' => $filters,
            'isAdmin' => true,
            'scopeLabel' => 'Organization-wide reporting',
            'department' => null,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()
                ->with(['user:id,name', 'department:id,name'])
                ->orderBy('employee_number')
                ->get(['id', 'user_id', 'department_id', 'employee_number']),
            'reportRoute' => route('admin.reports.attendance.index'),
            'bulkRoute' => route('admin.reports.dtr.bulk'),
            'selectedMonth' => now(config('app.timezone'))->format('Y-m'),
        ]);
    }
}
