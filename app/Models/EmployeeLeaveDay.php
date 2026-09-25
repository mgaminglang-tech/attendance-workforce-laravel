<?php

namespace App\Models;

use Database\Factories\EmployeeLeaveDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_date'])]
class EmployeeLeaveDay extends Model
{
    /** @use HasFactory<EmployeeLeaveDayFactory> */
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
        ];
    }
}
