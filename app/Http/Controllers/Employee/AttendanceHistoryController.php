<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmployeeAttendanceHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceHistoryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, EmployeeAttendanceHistory $attendanceHistory): View
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee()->firstOrFail();

        return view('employee.attendance.history', [
            'historyRecords' => $attendanceHistory->for($employee),
        ]);
    }
}
