<?php

namespace App\Actions\Employees;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Mail\EmployeeInvitationMail;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateInvitedEmployee
{
    public function __construct(
        private CreateEmployeeProfile $createEmployeeProfile,
        private IssueEmployeeInvitation $issueEmployeeInvitation,
    ) {}

    public function handle(
        string $firstName,
        string $lastName,
        string $email,
        string $employeeNumber,
        ?Department $department = null,
        ?string $jobTitle = null,
        ?DateTimeInterface $hiredAt = null,
    ): Employee {
        $firstName = Str::squish($firstName);
        $lastName = Str::squish($lastName);

        /** @var array{employee: Employee, user: User, token: string} $result */
        $result = DB::transaction(function () use ($firstName, $lastName, $email, $employeeNumber, $department, $jobTitle, $hiredAt): array {
            $user = new User;
            $user->forceFill([
                'name' => $firstName.' '.$lastName,
                'email' => $email,
                'password' => null,
                'role' => UserRole::Employee,
                'account_status' => AccountStatus::Pending,
            ])->save();

            $employee = $this->createEmployeeProfile->handle(
                user: $user,
                employeeNumber: $employeeNumber,
                department: $department,
                jobTitle: $jobTitle,
                hiredAt: $hiredAt,
                firstName: $firstName,
                lastName: $lastName,
            );

            return [
                'employee' => $employee,
                'user' => $user,
                'token' => $this->issueEmployeeInvitation->handle($user),
            ];
        });

        Mail::to($result['user'])->send(new EmployeeInvitationMail($result['user'], $result['token']));

        return $result['employee'];
    }
}
