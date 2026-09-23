<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Attendance\TimeInEmployee;
use App\Exceptions\AttendanceActionException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimeInController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, TimeInEmployee $timeInEmployee): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $timeInEmployee->handle($user);
        } catch (AttendanceActionException $exception) {
            return redirect()
                ->route('employee.attendance.index')
                ->withErrors(['attendance' => $exception->getMessage()]);
        }

        return redirect()
            ->route('employee.attendance.index')
            ->with('status', 'You are now timed in.');
    }
}
