<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Employees\CreateInvitedEmployee;
use App\Actions\Employees\UpdateEmployeeProfile;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $departmentId = $request->integer('department');
        $accountStatus = $request->enum('account_status', AccountStatus::class);

        $employees = Employee::query()
            ->with(['user', 'department', 'user.employeeInvitation'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($departmentId > 0, fn ($query) => $query->where('department_id', $departmentId))
            ->when($accountStatus !== null, fn ($query) => $query->whereHas(
                'user',
                fn ($query) => $query->where('account_status', $accountStatus->value),
            ))
            ->orderBy('employee_number')
            ->paginate(15)
            ->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('name')->get(),
            'accountStatuses' => AccountStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.create', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreEmployeeRequest $request, CreateInvitedEmployee $createInvitedEmployee): RedirectResponse
    {
        $employee = $createInvitedEmployee->handle(
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            email: $request->string('email')->toString(),
            employeeNumber: $request->string('employee_number')->toString(),
            department: $request->filled('department_id')
                ? Department::findOrFail($request->integer('department_id'))
                : null,
            jobTitle: $request->filled('job_title') ? $request->string('job_title')->toString() : null,
            hiredAt: $request->filled('hired_at') ? $request->date('hired_at') : null,
        );

        return redirect()
            ->route('admin.employees.show', $employee)
            ->with('status', 'Employee created and invitation sent.');
    }

    public function show(Employee $employee): View
    {
        return view('admin.employees.show', [
            'employee' => $employee->load(['user.employeeInvitation', 'department']),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('admin.employees.edit', [
            'employee' => $employee->load('user'),
            'departments' => Department::query()
                ->where('is_active', true)
                ->when(
                    $employee->department_id !== null,
                    fn ($query) => $query->orWhereKey($employee->department_id),
                )
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee,
        UpdateEmployeeProfile $updateEmployeeProfile,
    ): RedirectResponse {
        $updateEmployeeProfile->handle(
            employee: $employee,
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            email: $request->string('email')->toString(),
            employeeNumber: $request->string('employee_number')->toString(),
            department: $request->filled('department_id')
                ? Department::findOrFail($request->integer('department_id'))
                : null,
            jobTitle: $request->filled('job_title') ? $request->string('job_title')->toString() : null,
            hiredAt: $request->filled('hired_at') ? $request->date('hired_at') : null,
        );

        return redirect()
            ->route('admin.employees.show', $employee)
            ->with('status', 'Employee details updated.');
    }
}
