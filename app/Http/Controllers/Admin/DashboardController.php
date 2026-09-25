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
        $notClockedIn = Employee::query()
            ->whereDoesntHave('attendanceSessions', fn ($query) => $query
                ->whereNull('time_out_at')
                ->orWhereDate('work_date', $workDate))
            ->count();

        return view('admin.dashboard', [
            'summary' => [
                'employees' => Employee::query()->count(),
                'working' => AttendanceSession::query()->whereNull('time_out_at')->count(),
                'completed_today' => AttendanceSession::query()
                    ->whereDate('work_date', $workDate)
                    ->whereNotNull('time_out_at')
                    ->count(),
                'not_clocked_in' => $notClockedIn,
            ],
            'recentSessions' => AttendanceSession::query()
                ->with(['employee.user', 'employee.department'])
                ->where(function ($query) use ($workDate): void {
                    $query->whereDate('work_date', $workDate)
                        ->orWhereNull('time_out_at');
                })
                ->orderByDesc('work_date')
                ->orderByDesc('time_in_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'departments' => Department::query()
                ->with('hrAssignment.user:id,name')
                ->withCount([
                    'employees',
                    'employees as working_count' => fn ($query) => $query
                        ->whereHas('attendanceSessions', fn ($query) => $query->whereNull('time_out_at')),
                ])
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(6)
                ->get(),
            'workDate' => $workDate,
        ]);
    }
}
