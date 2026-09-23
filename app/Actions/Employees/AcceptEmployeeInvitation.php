<?php

namespace App\Actions\Employees;

use App\Enums\AccountStatus;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AcceptEmployeeInvitation
{
    public function handle(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password): User {
            $invitation = EmployeeInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->firstOrFail();

            $user = User::query()->lockForUpdate()->findOrFail($invitation->user_id);

            if (
                $invitation->accepted_at !== null
                || $invitation->expires_at->isPast()
                || $user->account_status !== AccountStatus::Pending
            ) {
                throw (new ModelNotFoundException)->setModel(EmployeeInvitation::class);
            }

            $user->forceFill([
                'password' => $password,
                'account_status' => AccountStatus::Active,
                'email_verified_at' => now(),
            ])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }
}
