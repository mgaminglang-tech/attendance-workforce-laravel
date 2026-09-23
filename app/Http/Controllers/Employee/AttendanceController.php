<?php

namespace App\Http\Controllers\Employee;

use App\Enums\WorkArrangement;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee()->firstOrFail();
        $workDate = now(config('app.timezone'))->toDateString();

        $currentSession = $employee->attendanceSessions()
            ->whereNull('time_out_at')
            ->latest('time_in_at')
            ->first();

        $currentSession ??= $employee->attendanceSessions()
            ->whereDate('work_date', $workDate)
            ->latest('time_in_at')
            ->first();

        return view('employee.attendance.index', [
            'currentSession' => $currentSession,
            'employee' => $employee,
            'workDate' => $workDate,
            'workArrangements' => WorkArrangement::cases(),
        ]);
    }
}
