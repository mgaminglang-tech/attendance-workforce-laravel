<?php

namespace App\Services;

use App\Models\AttendanceSession;

class CalculateNetAttendanceMinutes
{
    private const BREAK_THRESHOLD_MINUTES = 360;

    public function handle(AttendanceSession $attendanceSession): ?int
    {
        $grossMinutes = $attendanceSession->workedMinutes();

        if ($grossMinutes === null) {
            return null;
        }

        $grossMinutes = max(0, $grossMinutes);

        if ($grossMinutes < self::BREAK_THRESHOLD_MINUTES) {
            return $grossMinutes;
        }

        $breakMinutes = max(0, (int) config('workforce.dtr.break_minutes'));

        return max(0, $grossMinutes - $breakMinutes);
    }
}
