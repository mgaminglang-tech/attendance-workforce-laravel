<?php

namespace App\Actions\Employees;

use App\Models\EmployeeInvitation;
use App\Models\User;

class IssueEmployeeInvitation
{
    public function handle(User $user, ?EmployeeInvitation $invitation = null): string
    {
        $token = bin2hex(random_bytes(32));

        $invitation ??= new EmployeeInvitation;
        $invitation->forceFill([
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(config('workforce.invitations.expiration_minutes')),
            'accepted_at' => null,
        ])->save();

        return $token;
    }
}
