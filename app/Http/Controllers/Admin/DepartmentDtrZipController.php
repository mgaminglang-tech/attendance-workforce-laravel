<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BulkDtrGenerationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentDtrZipRequest;
use App\Models\Department;
use App\Services\BuildDepartmentDtrZip;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DepartmentDtrZipController extends Controller
{
    public function __invoke(
        DepartmentDtrZipRequest $request,
        BuildDepartmentDtrZip $buildDepartmentDtrZip,
    ): BinaryFileResponse {
        $department = Department::query()->findOrFail($request->integer('department'));
        Gate::authorize('viewReports', $department);
        $archive = $buildDepartmentDtrZip->handle($department, $request->selectedMonth());

        try {
            return response()->download($archive['path'], $archive['filename'], [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->setPrivate()->deleteFileAfterSend();
        } catch (Throwable $exception) {
            File::delete($archive['path']);

            throw new BulkDtrGenerationException(
                departmentId: $department->getKey(),
                month: $request->selectedMonth()->format('Y-m'),
                previous: $exception,
            );
        }
    }
}
