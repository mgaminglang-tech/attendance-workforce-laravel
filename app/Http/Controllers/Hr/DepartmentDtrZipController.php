<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentDtrZipRequest;
use App\Models\User;
use App\Services\BuildDepartmentDtrZip;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DepartmentDtrZipController extends Controller
{
    public function __invoke(
        DepartmentDtrZipRequest $request,
        BuildDepartmentDtrZip $buildDepartmentDtrZip,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $department = $user->hrDepartmentAssignment()
            ->with('department')
            ->firstOrFail()
            ->department;

        Gate::authorize('viewReports', $department);
        $archive = $buildDepartmentDtrZip->handle($department, $request->selectedMonth());

        return response($archive['content'], headers: [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $archive['filename'],
            ),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
