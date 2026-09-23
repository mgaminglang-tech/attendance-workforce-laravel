<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Attendance\TimeInEmployee;
use App\Exceptions\AttendanceActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\TimeInRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TimeInController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(TimeInRequest $request, TimeInEmployee $timeInEmployee): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $timeInEmployee->handle($user, $request->workArrangement());
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
