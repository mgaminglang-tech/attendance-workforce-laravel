<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): View
    {
        $workDate = now(config('app.timezone'))->toDateString();

        return view('admin.dashboard', [
            'summary' => [
                'employees' => Employee::query()->count(),
                'working' => AttendanceSession::query()->whereNull('time_out_at')->count(),
                'completed_today' => AttendanceSession::query()
                    ->whereDate('work_date', $workDate)
                    ->whereNotNull('time_out_at')
                    ->count(),
                'active_departments' => Department::query()->where('is_active', true)->count(),
            ],
            'recentSessions' => AttendanceSession::query()
                ->with(['employee.user', 'employee.department'])
                ->orderByDesc('work_date')
                ->orderByDesc('time_in_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'workDate' => $workDate,
        ]);
    }
}
