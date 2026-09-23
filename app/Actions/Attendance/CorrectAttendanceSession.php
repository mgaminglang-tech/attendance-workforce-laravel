<?php

namespace App\Actions\Attendance;

use App\Enums\WorkArrangement;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorrectAttendanceSession
{
    /** @throws ValidationException */
    public function handle(
        User $administrator,
        AttendanceSession $attendanceSession,
        CarbonImmutable $correctedTimeInAt,
        ?CarbonImmutable $correctedTimeOutAt,
        ?WorkArrangement $correctedWorkArrangement,
        string $reason,
    ): AttendanceAdjustment {
        Gate::forUser($administrator)->authorize('manage-workforce');

        $reason = Str::squish($reason);

        if (Str::length($reason) < 10 || preg_match('/\pL/u', $reason) !== 1) {
            throw ValidationException::withMessages([
                'reason' => 'Provide a meaningful correction reason of at least 10 characters.',
            ]);
        }

        $timezone = config('app.timezone');
        $correctedTimeInAt = $correctedTimeInAt->setTimezone($timezone);
        $correctedTimeOutAt = $correctedTimeOutAt?->setTimezone($timezone);

        if ($correctedTimeOutAt !== null && $correctedTimeOutAt->lessThanOrEqualTo($correctedTimeInAt)) {
            throw ValidationException::withMessages([
                'time_out_at' => 'Corrected Time Out must be after corrected Time In.',
            ]);
        }

        return DB::transaction(function () use (
            $administrator,
            $attendanceSession,
            $correctedTimeInAt,
            $correctedTimeOutAt,
            $correctedWorkArrangement,
            $reason,
            $timezone,
        ): AttendanceAdjustment {
            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($attendanceSession->employee_id);

            $lockedSession = AttendanceSession::query()
                ->whereBelongsTo($employee)
                ->lockForUpdate()
                ->findOrFail($attendanceSession->getKey());

            $timeInChanged = ! $correctedTimeInAt->equalTo($lockedSession->time_in_at);
            $timeOutChanged = match (true) {
                $correctedTimeOutAt === null && $lockedSession->time_out_at === null => false,
                $correctedTimeOutAt === null || $lockedSession->time_out_at === null => true,
                default => ! $correctedTimeOutAt->equalTo($lockedSession->time_out_at),
            };
            $workArrangementChanged = $correctedWorkArrangement !== $lockedSession->work_arrangement;

            if ($lockedSession->work_arrangement !== null && $correctedWorkArrangement === null) {
                throw ValidationException::withMessages([
                    'work_arrangement' => 'A recorded Work Arrangement cannot be cleared.',
                ]);
            }

            if (! $timeInChanged && ! $timeOutChanged && ! $workArrangementChanged) {
                throw ValidationException::withMessages([
                    'work_arrangement' => 'Change at least one attendance value before saving a correction.',
                ]);
            }

            $correctedWorkDate = $timeInChanged
                ? $correctedTimeInAt->toDateString()
                : $lockedSession->work_date->toDateString();

            if (
                $correctedWorkDate !== $lockedSession->work_date->toDateString()
                && AttendanceSession::query()
                    ->whereBelongsTo($employee)
                    ->whereKeyNot($lockedSession->getKey())
                    ->whereDate('work_date', $correctedWorkDate)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'time_in_at' => 'An attendance session already exists for this employee on the corrected work date.',
                ]);
            }

            $previousWorkDate = $lockedSession->work_date;
            $previousTimeInAt = $lockedSession->time_in_at;
            $previousTimeOutAt = $lockedSession->time_out_at;
            $beforeWorkArrangement = $lockedSession->work_arrangement;

            $lockedSession->forceFill([
                'work_date' => $correctedWorkDate,
                'time_in_at' => $correctedTimeInAt,
                'time_out_at' => $correctedTimeOutAt,
                'work_arrangement' => $correctedWorkArrangement,
            ])->save();

            $adjustment = new AttendanceAdjustment;
            $adjustment->forceFill([
                'attendance_session_id' => $lockedSession->getKey(),
                'administrator_id' => $administrator->getKey(),
                'reason' => $reason,
                'previous_work_date' => $previousWorkDate,
                'previous_time_in_at' => $previousTimeInAt,
                'previous_time_out_at' => $previousTimeOutAt,
                'before_work_arrangement' => $beforeWorkArrangement,
                'corrected_work_date' => $correctedWorkDate,
                'corrected_time_in_at' => $correctedTimeInAt,
                'corrected_time_out_at' => $correctedTimeOutAt,
                'after_work_arrangement' => $correctedWorkArrangement,
                'corrected_at' => now($timezone),
            ])->save();

            return $adjustment->setRelation('attendanceSession', $lockedSession);
        }, attempts: 3);
    }
}
