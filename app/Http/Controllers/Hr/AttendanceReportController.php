<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceReportRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceReportQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    public function index(
        AttendanceReportRequest $request,
        AttendanceReportQuery $attendanceReportQuery,
    ): View {
        /** @var User $user */
        $user = $request->user();
        $department = $user->hrDepartmentAssignment()
            ->with('department')
            ->firstOrFail()
            ->department;

        Gate::authorize('viewReports', $department);
        $filters = $request->filters($department->getKey());

        return view('reports.attendance', [
            ...$attendanceReportQuery->handle($filters),
            'filters' => $filters,
            'isAdmin' => false,
            'scopeLabel' => 'HR Representative workspace',
            'department' => $department,
            'departments' => collect(),
            'employees' => Employee::query()
                ->whereBelongsTo($department)
                ->with('user:id,name')
                ->orderBy('employee_number')
                ->get(['id', 'user_id', 'department_id', 'employee_number']),
            'reportRoute' => route('hr.reports.attendance.index'),
            'bulkRoute' => route('hr.reports.dtr.bulk'),
            'selectedMonth' => now(config('app.timezone'))->format('Y-m'),
        ]);
    }
}
