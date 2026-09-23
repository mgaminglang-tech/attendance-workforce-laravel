<?php

namespace App\Models;

use Database\Factories\AttendanceAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AttendanceAdjustment extends Model
{
    /** @use HasFactory<AttendanceAdjustmentFactory> */
    use HasFactory;

    public $timestamps = false;

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_work_date' => 'date',
            'previous_time_in_at' => 'immutable_datetime',
            'previous_time_out_at' => 'immutable_datetime',
            'corrected_work_date' => 'date',
            'corrected_time_in_at' => 'immutable_datetime',
            'corrected_time_out_at' => 'immutable_datetime',
            'corrected_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Attendance adjustment history is immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Attendance adjustment history is immutable.');
        });
    }
}
