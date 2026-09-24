<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmployeeAttendanceOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, EmployeeAttendanceOverview $attendanceOverview): View
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee()->with('department')->first();
        $hrDepartment = $user->hrDepartmentAssignment()->with('department')->first()?->department;

        return view('employee.dashboard', [
            ...($employee === null ? [] : $attendanceOverview->for($employee)),
            'employee' => $employee,
            'hrDepartment' => $hrDepartment,
        ]);
    }
}
