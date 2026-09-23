<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Employees\ResendEmployeeInvitation;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;

class EmployeeInvitationController extends Controller
{
    public function __invoke(Employee $employee, ResendEmployeeInvitation $resendEmployeeInvitation): RedirectResponse
    {
        $resendEmployeeInvitation->handle($employee);

        return back()->with('status', 'A new invitation was sent.');
    }
}
