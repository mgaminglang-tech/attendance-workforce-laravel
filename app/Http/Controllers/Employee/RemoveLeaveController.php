<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Attendance\RemoveEmployeeLeave;
use App\Exceptions\LeaveActionException;
use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RemoveLeaveController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        EmployeeLeaveDay $leaveDay,
        RemoveEmployeeLeave $removeEmployeeLeave,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee()->firstOrFail();
        $ownedLeaveDay = $employee->leaveDays()->findOrFail($leaveDay->getKey());

        try {
            $removeEmployeeLeave->handle($employee, $ownedLeaveDay);
        } catch (LeaveActionException $exception) {
            return redirect()
                ->route('employee.attendance.index')
                ->withErrors(['leave_remove' => $exception->getMessage()]);
        }

        return redirect()
            ->route('employee.attendance.index')
            ->with('status', 'Leave record removed.');
    }
}
