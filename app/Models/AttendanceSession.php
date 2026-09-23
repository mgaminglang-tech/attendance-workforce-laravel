<?php

namespace App\Models;

use Database\Factories\AttendanceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['work_date', 'time_in_at', 'time_out_at'])]
class AttendanceSession extends Model
{
    /** @use HasFactory<AttendanceSessionFactory> */
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class);
    }

    public function workedMinutes(): ?int
    {
        if ($this->time_out_at === null) {
            return null;
        }

        return intdiv(
            $this->time_out_at->getTimestamp() - $this->time_in_at->getTimestamp(),
            60,
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'time_in_at' => 'immutable_datetime',
            'time_out_at' => 'immutable_datetime',
        ];
    }
}
