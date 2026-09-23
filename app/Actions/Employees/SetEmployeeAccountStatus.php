<?php

namespace App\Actions\Employees;

use App\Enums\AccountStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetEmployeeAccountStatus
{
    /**
     * @throws ValidationException
     */
    public function handle(Employee $employee, AccountStatus $status): User
    {
        if (! in_array($status, [AccountStatus::Active, AccountStatus::Disabled], true)) {
            throw ValidationException::withMessages([
                'account_status' => 'Pending accounts must be activated through their invitation.',
            ]);
        }

        $user = DB::transaction(function () use ($employee, $status): User {
            $user = User::query()->lockForUpdate()->findOrFail($employee->user_id);

            if ($user->account_status === AccountStatus::Pending || $user->password === null) {
                throw ValidationException::withMessages([
                    'account_status' => 'Pending accounts must be activated through their invitation.',
                ]);
            }

            $user->forceFill(['account_status' => $status])->save();

            if ($status === AccountStatus::Disabled) {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            return $user;
        });

        return $user;
    }
}
