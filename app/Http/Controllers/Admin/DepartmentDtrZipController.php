<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentDtrZipRequest;
use App\Models\Department;
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
        $department = Department::query()->findOrFail($request->integer('department'));
        Gate::authorize('viewReports', $department);
        $archive = $buildDepartmentDtrZip->handle($department, $request->selectedMonth());

        return $this->response($archive['content'], $archive['filename']);
    }

    private function response(string $content, string $filename): Response
    {
        return response($content, headers: [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
            ),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
