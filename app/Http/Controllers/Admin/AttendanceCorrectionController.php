<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\CorrectAttendanceSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrectAttendanceSessionRequest;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function edit(AttendanceSession $attendanceSession): View
    {
        return view('admin.attendance.correct', [
            'attendanceSession' => $attendanceSession->load(['employee.user', 'employee.department']),
        ]);
    }

    public function update(
        CorrectAttendanceSessionRequest $request,
        AttendanceSession $attendanceSession,
        CorrectAttendanceSession $correctAttendanceSession,
    ): RedirectResponse {
        /** @var User $administrator */
        $administrator = $request->user();

        $correctAttendanceSession->handle(
            administrator: $administrator,
            attendanceSession: $attendanceSession,
            correctedTimeInAt: $request->correctedTimeInAt(),
            correctedTimeOutAt: $request->correctedTimeOutAt(),
            correctedWorkArrangement: $request->correctedWorkArrangement(),
            reason: $request->string('reason')->toString(),
        );

        return redirect()
            ->route('admin.attendance.show', $attendanceSession)
            ->with('status', 'Attendance correction saved with an immutable audit record.');
    }
}
