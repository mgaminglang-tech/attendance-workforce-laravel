<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\BulkDtrGenerationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentDtrZipRequest;
use App\Models\User;
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
        /** @var User $user */
        $user = $request->user();
        $department = $user->hrDepartmentAssignment()
            ->with('department')
            ->firstOrFail()
            ->department;

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
