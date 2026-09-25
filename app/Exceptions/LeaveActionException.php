<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class LeaveActionException extends Exception implements ShouldntReport
{
    public static function employmentUnavailable(): self
    {
        return new self('Your employment status does not permit leave actions.');
    }

    public static function attendanceConflict(): self
    {
        return new self('Leave cannot be recorded for a date that already has attendance.');
    }

    public static function duplicateLeave(): self
    {
        return new self('Leave has already been recorded for one or more selected dates.');
    }

    public static function pastLeaveCannotBeRemoved(): self
    {
        return new self('Past leave records cannot be removed.');
    }
}
