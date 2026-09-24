<?php

namespace App\Services;

use App\Exceptions\BulkDtrGenerationException;
use App\Models\Department;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class BuildDepartmentDtrZip
{
    public function __construct(
        private BuildMonthlyDtr $buildMonthlyDtr,
        private MonthlyDtrPdf $monthlyDtrPdf,
        private Filesystem $files,
    ) {}

    /** @return array{filename: string, content: string, employee_count: int} */
    public function handle(Department $department, CarbonImmutable $selectedMonth): array
    {
        $month = $selectedMonth->format('Y-m');
        $directory = storage_path('app/private/dtr-bulk');
        $this->files->ensureDirectoryExists($directory, 0700);
        $temporaryZipPath = $directory.DIRECTORY_SEPARATOR.'dtr-'.Str::uuid().'.zip';
        $archive = null;
        $archiveIsOpen = false;

        try {
            $employees = Employee::query()
                ->whereBelongsTo($department)
                ->with(['user:id,name', 'department:id,name'])
                ->orderBy('employee_number')
                ->orderBy('id')
                ->get();

            $archive = new ZipArchive;
            $openResult = $archive->open($temporaryZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($openResult !== true) {
                throw new RuntimeException("Unable to create DTR ZIP archive (code {$openResult}).");
            }

            $archiveIsOpen = true;
            $usedFilenames = [];

            foreach ($employees as $employee) {
                $dtr = $this->buildMonthlyDtr->handle($employee, $selectedMonth);
                $filename = $this->uniqueFilename(
                    $this->monthlyDtrPdf->filename($dtr),
                    $employee,
                    $usedFilenames,
                );

                if (! $archive->addFromString($filename, $this->monthlyDtrPdf->render($dtr))) {
                    throw new RuntimeException("Unable to add {$filename} to the DTR ZIP archive.");
                }

                $usedFilenames[$filename] = true;
            }

            $archiveClosed = $archive->close();
            $archiveIsOpen = false;

            if (! $archiveClosed) {
                throw new RuntimeException('Unable to finalize the DTR ZIP archive.');
            }

            return [
                'filename' => $this->archiveFilename($department, $month),
                'content' => $this->files->get($temporaryZipPath),
                'employee_count' => $employees->count(),
            ];
        } catch (Throwable $exception) {
            throw new BulkDtrGenerationException(
                departmentId: $department->getKey(),
                month: $month,
                previous: $exception,
            );
        } finally {
            if ($archiveIsOpen) {
                $archive?->close();
            }

            $this->files->delete($temporaryZipPath);
        }
    }

    /** @param array<string, bool> $usedFilenames */
    private function uniqueFilename(string $filename, Employee $employee, array $usedFilenames): string
    {
        if (! isset($usedFilenames[$filename])) {
            return $filename;
        }

        return pathinfo($filename, PATHINFO_FILENAME).'_'.$employee->getKey().'.pdf';
    }

    private function archiveFilename(Department $department, string $month): string
    {
        $safeDepartmentName = Str::of($department->name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->trim('_-')
            ->value();

        if ($safeDepartmentName === '') {
            $safeDepartmentName = 'DEPARTMENT_'.$department->getKey();
        }

        return "{$safeDepartmentName}_DTR_{$month}.zip";
    }
}
