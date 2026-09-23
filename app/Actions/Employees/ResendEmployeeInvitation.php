<?php

namespace App\Actions\Employees;

use App\Enums\AccountStatus;
use App\Mail\EmployeeInvitationMail;
use App\Models\Employee;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ResendEmployeeInvitation
{
    public function __construct(private IssueEmployeeInvitation $issueEmployeeInvitation) {}

    /**
     * @throws ValidationException
     */
    public function handle(Employee $employee): void
    {
        /** @var array{user: User, token: string} $result */
        $result = DB::transaction(function () use ($employee): array {
            $user = User::query()->lockForUpdate()->findOrFail($employee->user_id);

            if ($user->account_status !== AccountStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => 'Invitations may only be resent to pending employees.',
                ]);
            }

            $invitation = EmployeeInvitation::query()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->firstOrFail();

            $cooldownEndsAt = $invitation->updated_at->addSeconds(
                config('workforce.invitations.resend_cooldown_seconds'),
            );

            if ($cooldownEndsAt->isFuture()) {
                throw ValidationException::withMessages([
                    'invitation' => 'Please wait before resending this invitation.',
                ]);
            }

            return [
                'user' => $user,
                'token' => $this->issueEmployeeInvitation->handle($user, $invitation),
            ];
        });

        Mail::to($result['user'])->send(new EmployeeInvitationMail($result['user'], $result['token']));
    }
}
