<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class AttendanceActionException extends Exception implements ShouldntReport
{
    public static function accountUnavailable(): self
    {
        return new self('Your account is not permitted to record attendance.');
    }

    public static function employmentUnavailable(): self
    {
        return new self('Your employment status does not permit attendance actions.');
    }

    public static function employeeProfileMissing(): self
    {
        return new self('No employee profile is available for this account.');
    }

    public static function alreadyTimedIn(): self
    {
        return new self('You are already timed in.');
    }

    public static function workDateCompleted(): self
    {
        return new self('Your attendance for today has already been completed.');
    }

    public static function noOpenSession(): self
    {
        return new self('No active attendance session was found.');
    }

    public static function timeOutPrecedesTimeIn(): self
    {
        return new self('Time Out cannot be earlier than Time In.');
    }
}
