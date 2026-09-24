<?php

namespace App\Services;

use App\Models\AttendanceSession;

class CalculateNetAttendanceMinutes
{
    public function handle(AttendanceSession $attendanceSession): ?int
    {
        $grossMinutes = $attendanceSession->workedMinutes();

        if ($grossMinutes === null) {
            return null;
        }

        $breakMinutes = max(0, (int) config('workforce.dtr.break_minutes'));

        return max(0, $grossMinutes - $breakMinutes);
    }
}
