<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmployeeAttendanceOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, EmployeeAttendanceOverview $attendanceOverview): View
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee()->firstOrFail();

        return view('employee.attendance.index', [
            ...$attendanceOverview->for($employee),
            'employee' => $employee,
        ]);
    }
}
