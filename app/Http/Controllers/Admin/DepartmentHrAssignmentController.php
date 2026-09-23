<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Departments\AssignDepartmentHrRepresentative;
use App\Actions\Departments\RemoveDepartmentHrRepresentative;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignDepartmentHrRepresentativeRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DepartmentHrAssignmentController extends Controller
{
    public function update(
        AssignDepartmentHrRepresentativeRequest $request,
        Department $department,
        AssignDepartmentHrRepresentative $assignDepartmentHrRepresentative,
    ): RedirectResponse {
        /** @var User $administrator */
        $administrator = $request->user();
        $representative = User::query()->findOrFail($request->integer('user_id'));

        $assignDepartmentHrRepresentative->handle($administrator, $department, $representative);

        return redirect()
            ->route('admin.departments.edit', $department)
            ->with('status', 'HR Representative assignment updated.');
    }

    public function destroy(
        Department $department,
        RemoveDepartmentHrRepresentative $removeDepartmentHrRepresentative,
    ): RedirectResponse {
        /** @var User $administrator */
        $administrator = request()->user();
        $removeDepartmentHrRepresentative->handle($administrator, $department);

        return redirect()
            ->route('admin.departments.edit', $department)
            ->with('status', 'HR Representative assignment removed.');
    }
}
