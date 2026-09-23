<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\TeamAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeamAttendanceController extends Controller
{
    public function __construct(private TeamAttendanceService $teamAttendanceService) {}

    public function employee(Request $request): View
    {
        $department = $this->employeeDepartment($request);

        if ($department === null) {
            return view('team-attendance.unavailable', [
                'message' => 'Your employee profile is not assigned to a department.',
            ]);
        }

        return $this->renderWorkspace($department, 'Own department', route('team-attendance.status'));
    }

    public function employeeStatus(Request $request): JsonResponse
    {
        $department = $this->employeeDepartment($request);
        abort_if($department === null, 404);

        return $this->statusResponse($department);
    }

    public function hr(Request $request): View
    {
        $department = $this->hrDepartment($request);
        abort_if($department === null, 403);

        return $this->renderWorkspace($department, 'HR Representative workspace', route('hr.team-attendance.status'));
    }

    public function hrStatus(Request $request): JsonResponse
    {
        $department = $this->hrDepartment($request);
        abort_if($department === null, 403);

        return $this->statusResponse($department);
    }

    public function adminIndex(): View
    {
        Gate::authorize('manage-workforce');

        return view('admin.team-attendance.index', [
            'departments' => Department::query()
                ->with('hrAssignment.user:id,name')
                ->withCount('employees')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function admin(Department $department): View
    {
        return $this->renderWorkspace(
            $department,
            'Global Admin workspace',
            route('admin.departments.team-attendance.status', $department),
        );
    }

    public function adminStatus(Department $department): JsonResponse
    {
        return $this->statusResponse($department);
    }

    private function renderWorkspace(Department $department, string $accessLabel, string $statusUrl): View
    {
        Gate::authorize('viewTeamAttendance', $department);

        return view('team-attendance.show', [
            'teamAttendance' => $this->teamAttendanceService->forDepartment($department),
            'accessLabel' => $accessLabel,
            'statusUrl' => $statusUrl,
        ]);
    }

    private function statusResponse(Department $department): JsonResponse
    {
        Gate::authorize('viewTeamAttendance', $department);

        return response()->json($this->teamAttendanceService->forDepartment($department));
    }

    private function employeeDepartment(Request $request): ?Department
    {
        /** @var User $user */
        $user = $request->user();

        return $user->employee()->with('department')->first()?->department;
    }

    private function hrDepartment(Request $request): ?Department
    {
        /** @var User $user */
        $user = $request->user();

        return $user->hrDepartmentAssignment()->with('department')->first()?->department;
    }
}
