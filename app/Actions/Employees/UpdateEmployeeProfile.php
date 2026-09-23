<?php

namespace App\Actions\Employees;

use App\Enums\AccountStatus;
use App\Mail\EmployeeInvitationMail;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeInvitation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class UpdateEmployeeProfile
{
    public function __construct(private IssueEmployeeInvitation $issueEmployeeInvitation) {}

    public function handle(
        Employee $employee,
        string $name,
        string $email,
        string $employeeNumber,
        ?Department $department = null,
        ?string $jobTitle = null,
        ?DateTimeInterface $hiredAt = null,
    ): Employee {
        /** @var array{employee: Employee, user: User, token: string|null} $result */
        $result = DB::transaction(function () use ($employee, $name, $email, $employeeNumber, $department, $jobTitle, $hiredAt): array {
            $user = User::query()->lockForUpdate()->findOrFail($employee->user_id);
            $shouldRotateInvitation = $user->account_status === AccountStatus::Pending
                && $user->email !== $email;

            $user->update([
                'name' => $name,
                'email' => $email,
            ]);

            $employee->update([
                'employee_number' => $employeeNumber,
                'department_id' => $department?->getKey(),
                'job_title' => $jobTitle,
                'hired_at' => $hiredAt,
            ]);

            $token = null;

            if ($shouldRotateInvitation) {
                $invitation = EmployeeInvitation::query()
                    ->whereBelongsTo($user)
                    ->lockForUpdate()
                    ->firstOrFail();
                $token = $this->issueEmployeeInvitation->handle($user, $invitation);
            }

            return [
                'employee' => $employee->refresh(),
                'user' => $user,
                'token' => $token,
            ];
        });

        if ($result['token'] !== null) {
            Mail::to($result['user'])->send(new EmployeeInvitationMail($result['user'], $result['token']));
        }

        return $result['employee'];
    }
}
