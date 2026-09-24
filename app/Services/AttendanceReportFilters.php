<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class AttendanceReportFilters
{
    public function __construct(
        public CarbonImmutable $dateFrom,
        public CarbonImmutable $dateTo,
        public ?int $departmentId,
        public ?int $employeeId,
        public ?string $workArrangement,
        public ?string $state,
    ) {}
}
