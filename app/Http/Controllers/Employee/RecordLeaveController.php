<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Attendance\RecordEmployeeLeave;
use App\Exceptions\LeaveActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\RecordLeaveRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class RecordLeaveController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RecordLeaveRequest $request, RecordEmployeeLeave $recordEmployeeLeave): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee()->firstOrFail();

        try {
            $leaveDays = $recordEmployeeLeave->handle($employee, $request->fromDate(), $request->toDate());
        } catch (LeaveActionException $exception) {
            return redirect()
                ->route('employee.attendance.index')
                ->withInput()
                ->withErrors(['leave_record' => $exception->getMessage()]);
        }

        $dayLabel = $leaveDays->count() === 1 ? 'day' : 'days';

        return redirect()
            ->route('employee.attendance.index')
            ->with('status', "Leave recorded for {$leaveDays->count()} {$dayLabel}.");
    }
}
