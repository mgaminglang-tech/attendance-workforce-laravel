<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function employeeInvitation(): HasOne
    {
        return $this->hasOne(EmployeeInvitation::class);
    }

    public function attendanceAdjustmentsMade(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class, 'administrator_id');
    }

    public function hrDepartmentAssignment(): HasOne
    {
        return $this->hasOne(DepartmentHrAssignment::class);
    }

    public function hrAssignmentsMade(): HasMany
    {
        return $this->hasMany(DepartmentHrAssignment::class, 'assigned_by_user_id');
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'account_status' => AccountStatus::class,
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
