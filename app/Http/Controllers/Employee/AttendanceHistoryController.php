<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceHistoryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee()->firstOrFail();

        return view('employee.attendance.history', [
            'attendanceSessions' => $employee->attendanceSessions()
                ->orderByDesc('work_date')
                ->orderByDesc('time_in_at')
                ->orderByDesc('id')
                ->paginate(15),
        ]);
    }
}
