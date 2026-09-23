<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Employees\SetEmployeeAccountStatus;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEmployeeAccountRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;

class EmployeeAccountController extends Controller
{
    public function __invoke(
        UpdateEmployeeAccountRequest $request,
        Employee $employee,
        SetEmployeeAccountStatus $setEmployeeAccountStatus,
    ): RedirectResponse {
        $status = $request->enum('account_status', AccountStatus::class);
        abort_unless($status !== null, 422);

        $setEmployeeAccountStatus->handle($employee, $status);

        return back()->with('status', "Employee account {$status->value}.");
    }
}
