<?php

namespace App\Models;

use Database\Factories\DepartmentHrAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentHrAssignment extends Model
{
    /** @use HasFactory<DepartmentHrAssignmentFactory> */
    use HasFactory;

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime'];
    }
}
