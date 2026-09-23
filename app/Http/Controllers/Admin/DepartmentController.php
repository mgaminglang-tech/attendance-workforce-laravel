<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::query()
                ->withCount('employees')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.departments.create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department created.');
    }

    public function edit(Department $department): View
    {
        $department->load('hrAssignment.user');

        $eligibleRepresentatives = User::query()
            ->where('role', UserRole::Employee->value)
            ->where('account_status', AccountStatus::Active->value)
            ->whereHas('employee', fn ($query) => $query->where(
                'employment_status',
                EmploymentStatus::Active->value,
            ))
            ->where(function ($query) use ($department): void {
                $query->whereDoesntHave('hrDepartmentAssignment')
                    ->orWhereHas(
                        'hrDepartmentAssignment',
                        fn ($query) => $query->whereBelongsTo($department),
                    );
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.departments.edit', [
            'department' => $department,
            'eligibleRepresentatives' => $eligibleRepresentatives,
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department updated.');
    }
}
