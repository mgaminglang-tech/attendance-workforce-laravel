<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\DepartmentHrAssignment;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\User;
use App\Services\MonthlyDtrPdf;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class DepartmentDtrZipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_bulk_zip_contains_only_department_employees_and_cleans_temporary_files(): void
    {
        $admin = User::factory()->admin()->create();
        $finance = Department::factory()->create(['name' => 'Finance / Operations']);
        $it = Department::factory()->create(['name' => 'IT']);
        $first = Employee::factory()->for($finance)->create(['employee_number' => 'FIN-001']);
        Employee::factory()
            ->for(User::factory()->employee()->disabled())
            ->for($finance)
            ->inactive()
            ->create(['employee_number' => 'FIN-002']);
        Employee::factory()->for($finance)->create(['employee_number' => 'FIN 003/QA']);
        $foreign = Employee::factory()->for($it)->create(['employee_number' => 'IT-001']);
        AttendanceSession::factory()->for($first)->create(['work_date' => '2026-09-10']);
        AttendanceSession::factory()->for($foreign)->create(['work_date' => '2026-09-10']);
        $temporaryBefore = $this->temporaryZipFiles();

        $this->partialMock(Filesystem::class, function ($mock): void {
            $mock->shouldNotReceive('get')->with(Mockery::on(
                fn (string $path): bool => str_starts_with($path, storage_path('app/private/dtr-bulk')),
            ));
        });

        $response = $this->actingAs($admin)->get(route('admin.reports.dtr.bulk', [
            'department' => $finance->id,
            'month' => '2026-09',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/zip')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $this->assertStringContainsString(
            'filename=Finance_Operations_DTR_2026-09.zip',
            (string) $response->headers->get('content-disposition'),
        );

        $entries = $this->zipEntries($this->downloadContent($response));

        $this->assertSame([
            'DTR_FIN-001_2026-09.pdf',
            'DTR_FIN-002_2026-09.pdf',
            'DTR_FIN_003_QA_2026-09.pdf',
        ], array_keys($entries));
        $this->assertStringStartsWith('%PDF-', $entries['DTR_FIN-001_2026-09.pdf']);
        $this->assertStringStartsWith('%PDF-', $entries['DTR_FIN-002_2026-09.pdf']);
        $this->assertArrayNotHasKey('DTR_IT-001_2026-09.pdf', $entries);
        $this->assertSame($temporaryBefore, $this->temporaryZipFiles());

        $itResponse = $this->actingAs($admin)->get(route('admin.reports.dtr.bulk', [
            'department' => $it->id,
            'month' => '2026-09',
        ]));

        $itResponse->assertOk();
        $this->assertSame(
            ['DTR_IT-001_2026-09.pdf'],
            array_keys($this->zipEntries($this->downloadContent($itResponse))),
        );
    }

    public function test_bulk_zip_pdf_uses_the_shared_conditional_net_hours(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $employee = Employee::factory()->for($department)->create(['employee_number' => 'FIN-001']);
        AttendanceSession::factory()->for($employee)->create([
            'work_date' => '2026-09-10',
            'time_in_at' => '2026-09-10 08:00:00',
            'time_out_at' => '2026-09-10 13:22:00',
        ]);
        $this->mock(MonthlyDtrPdf::class, function ($mock): void {
            $mock->shouldReceive('filename')->once()->andReturn('DTR_FIN-001_2026-09.pdf');
            $mock->shouldReceive('render')
                ->once()
                ->withArgs(fn (array $dtr): bool => $dtr['rows'][9]['total_hours'] === '5.37')
                ->andReturn('%PDF-1.4 shared net hours');
        });

        $response = $this->actingAs($admin)->get(route('admin.reports.dtr.bulk', [
            'department' => $department->id,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $this->assertSame(
            '%PDF-1.4 shared net hours',
            $this->zipEntries($this->downloadContent($response))['DTR_FIN-001_2026-09.pdf'],
        );
    }

    public function test_bulk_zip_uses_the_shared_leave_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $employee = Employee::factory()->for($department)->create(['employee_number' => 'FIN-001']);
        EmployeeLeaveDay::factory()->for($employee)->create(['leave_date' => '2026-09-10']);
        $this->mock(MonthlyDtrPdf::class, function ($mock): void {
            $mock->shouldReceive('filename')->once()->andReturn('DTR_FIN-001_2026-09.pdf');
            $mock->shouldReceive('render')
                ->once()
                ->withArgs(fn (array $dtr): bool => $dtr['rows'][9]['attendance_rendered'] === 'ON LEAVE'
                    && $dtr['rows'][9]['time_in'] === ''
                    && $dtr['rows'][9]['total_hours'] === '')
                ->andReturn('%PDF-1.4 shared leave row');
        });

        $response = $this->actingAs($admin)->get(route('admin.reports.dtr.bulk', [
            'department' => $department->id,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $this->assertSame(
            '%PDF-1.4 shared leave row',
            $this->zipEntries($this->downloadContent($response))['DTR_FIN-001_2026-09.pdf'],
        );
    }

    public function test_hr_bulk_zip_uses_assignment_scope_and_rejects_department_tampering(): void
    {
        $finance = Department::factory()->create(['name' => 'Finance']);
        $it = Department::factory()->create(['name' => 'IT']);
        $representative = User::factory()->employee()->has(Employee::factory())->create();
        DepartmentHrAssignment::factory()->for($finance)->for($representative)->create();
        Employee::factory()->for($finance)->create(['employee_number' => 'FIN-001']);
        Employee::factory()->for($it)->create(['employee_number' => 'IT-001']);

        $response = $this->actingAs($representative)->get(route('hr.reports.dtr.bulk', [
            'month' => '2026-09',
        ]));

        $response->assertOk()
            ->assertDownload('Finance_DTR_2026-09.zip')
            ->assertHeader('content-type', 'application/zip')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $this->assertSame(
            ['DTR_FIN-001_2026-09.pdf'],
            array_keys($this->zipEntries($this->downloadContent($response))),
        );

        $this->actingAs($representative)->get(route('hr.reports.dtr.bulk', [
            'department' => $it->id,
            'month' => '2026-09',
        ]))->assertForbidden();
    }

    public function test_admin_can_generate_a_thirty_employee_department_archive(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Thirty Person Team']);

        foreach (range(1, 30) as $number) {
            Employee::factory()->for($department)->create([
                'employee_number' => sprintf('EMP-%04d', $number),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.reports.dtr.bulk', [
            'department' => $department->id,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $entries = $this->zipEntries($this->downloadContent($response));

        $this->assertCount(30, $entries);
        $this->assertArrayHasKey('DTR_EMP-0001_2026-09.pdf', $entries);
        $this->assertArrayHasKey('DTR_EMP-0030_2026-09.pdf', $entries);
    }

    public function test_bulk_routes_reject_invalid_input_and_unauthorized_employees(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $employee = User::factory()->employee()->has(Employee::factory())->create();

        $this->actingAs($admin)->get(route('admin.reports.dtr.bulk', [
            'month' => '2026-13',
        ]))->assertSessionHasErrors(['department', 'month']);

        $this->actingAs($employee)->get(route('hr.reports.dtr.bulk', [
            'department' => $department->id,
            'month' => '2026-09',
        ]))->assertForbidden();

        $this->actingAs($employee)->get(route('admin.reports.dtr.bulk', [
            'department' => $department->id,
            'month' => '2026-09',
        ]))->assertForbidden();
    }

    public function test_pdf_failure_returns_a_generic_error_and_removes_partial_archive(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        Employee::factory()->for($department)->create();
        $temporaryBefore = $this->temporaryZipFiles();
        $this->mock(MonthlyDtrPdf::class, function ($mock): void {
            $mock->shouldReceive('filename')->once()->andReturn('DTR_EMP-0001_2026-09.pdf');
            $mock->shouldReceive('render')->once()->andThrow(new RuntimeException('private path failure'));
        });

        $response = $this->actingAs($admin)->get(route('admin.reports.dtr.bulk', [
            'department' => $department->id,
            'month' => '2026-09',
        ]));

        $response->assertServerError()
            ->assertSee('The department DTR archive could not be generated. Please try again.')
            ->assertDontSee('private path failure');
        $this->assertSame($temporaryBefore, $this->temporaryZipFiles());
    }

    #[DataProvider('bulkRoutes')]
    public function test_zip_finalization_failure_cleans_up_and_returns_a_generic_error(string $routeName): void
    {
        $department = Department::factory()->create();
        Employee::factory()->for($department)->create();
        $user = $routeName === 'admin.reports.dtr.bulk'
            ? User::factory()->admin()->create()
            : User::factory()->employee()->has(Employee::factory())->create();

        if ($routeName === 'hr.reports.dtr.bulk') {
            DepartmentHrAssignment::factory()->for($department)->for($user)->create();
        }

        $originalStorage = storage_path();
        $temporaryStorage = storage_path('framework/testing/zip-finalization-'.Str::uuid());
        $cleanupPath = null;
        $this->app->useStoragePath($temporaryStorage);
        $directory = storage_path('app/private/dtr-bulk');
        $this->partialMock(Filesystem::class, function ($mock) use ($directory, &$cleanupPath): void {
            $mock->shouldReceive('delete')->once()
                ->withArgs(function (string $path) use ($directory, &$cleanupPath): bool {
                    $cleanupPath = $path;

                    return str_starts_with($path, $directory);
                })->passthru();
        });
        $this->mock(MonthlyDtrPdf::class, function ($mock) use ($directory): void {
            $mock->shouldReceive('filename')->once()->andReturn('DTR_EMP-0001_2026-09.pdf');
            $mock->shouldReceive('render')->once()->andReturnUsing(function () use ($directory): string {
                $this->assertTrue(rmdir($directory));

                return '%PDF-1.4 finalization failure fixture';
            });
        });

        try {
            $this->actingAs($user)->get(route($routeName, [
                'department' => $department->id,
                'month' => '2026-09',
            ]))->assertServerError()
                ->assertSee('The department DTR archive could not be generated. Please try again.')
                ->assertDontSee($temporaryStorage);

            $this->assertIsString($cleanupPath);
            $this->assertFileDoesNotExist($cleanupPath);
        } finally {
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($temporaryStorage);
        }
    }

    #[DataProvider('bulkRoutes')]
    public function test_response_creation_failure_removes_completed_archive(string $routeName): void
    {
        $department = Department::factory()->create();
        Employee::factory()->for($department)->create();
        $user = $routeName === 'admin.reports.dtr.bulk'
            ? User::factory()->admin()->create()
            : User::factory()->employee()->has(Employee::factory())->create();

        if ($routeName === 'hr.reports.dtr.bulk') {
            DepartmentHrAssignment::factory()->for($department)->for($user)->create();
        }

        $temporaryBefore = $this->temporaryZipFiles();
        $archivePath = null;
        $this->mock(MonthlyDtrPdf::class, function ($mock): void {
            $mock->shouldReceive('filename')->once()->andReturn('DTR_EMP-0001_2026-09.pdf');
            $mock->shouldReceive('render')->once()->andReturn('%PDF-1.4 response failure fixture');
        });
        Response::partialMock()->shouldReceive('download')
            ->once()
            ->withArgs(function (string $path, string $filename, array $headers) use (&$archivePath): bool {
                $archivePath = $path;
                $this->assertFileExists($path);

                return true;
            })
            ->andThrow(new RuntimeException('private response creation failure'));

        $this->actingAs($user)->get(route($routeName, [
            'department' => $department->id,
            'month' => '2026-09',
        ]))->assertServerError()
            ->assertSee('The department DTR archive could not be generated. Please try again.')
            ->assertDontSee('private response creation failure');

        $this->assertIsString($archivePath);
        $this->assertFileDoesNotExist($archivePath);
        $this->assertSame($temporaryBefore, $this->temporaryZipFiles());
    }

    /** @return array<string, array{string}> */
    public static function bulkRoutes(): array
    {
        return [
            'admin' => ['admin.reports.dtr.bulk'],
            'HR' => ['hr.reports.dtr.bulk'],
        ];
    }

    private function downloadContent(TestResponse $response): string
    {
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertFalse($response->getContent());
        $this->assertTrue($response->baseResponse->shouldDeleteFileAfterSend());
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertStringStartsWith(storage_path('app/private/dtr-bulk'), $path);
        $this->assertFileExists($path);
        $this->assertStringNotContainsString($path, (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();

        $this->assertFileDoesNotExist($path);

        return $content;
    }

    /** @return array<string, string> */
    private function zipEntries(string $content): array
    {
        $directory = storage_path('framework/testing');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'phase-8-'.Str::uuid().'.zip';
        File::put($path, $content);

        try {
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path) === true, 'The response must be a readable ZipArchive.');
            $entries = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $filename = $archive->getNameIndex($index);
                $contents = $archive->getFromIndex($index);

                $this->assertIsString($filename);
                $this->assertIsString($contents);
                $entries[$filename] = $contents;
            }

            ksort($entries);

            return $entries;
        } finally {
            $archive?->close();
            File::delete($path);
        }
    }

    /** @return list<string> */
    private function temporaryZipFiles(): array
    {
        return File::glob(storage_path('app/private/dtr-bulk/*.zip')) ?: [];
    }
}
