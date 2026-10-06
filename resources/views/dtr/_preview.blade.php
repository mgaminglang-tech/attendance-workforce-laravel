<section class="card surface-card mt-4" aria-labelledby="dtr-preview-heading">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
                <p class="detail-label mb-1">Monthly DTR preview</p>
                <h2 class="h4 mb-1" id="dtr-preview-heading">{{ $dtr['employee_name'] }}</h2>
                <p class="text-body-secondary mb-0">
                    {{ $dtr['employee_number'] }}
                    @if ($dtr['department_name'])
                        · {{ $dtr['department_name'] }}
                    @endif
                    · {{ $dtr['month_label'] }}
                </p>
            </div>
            <a class="btn btn-workforce dtr-download-button align-self-md-start" href="{{ $pdfUrl }}">
                <i class="ti ti-download me-1" aria-hidden="true"></i>Download PDF
            </a>
        </div>

        <p class="small text-body-secondary">
            Certification period: {{ $dtr['first_date_label'] }} to {{ $dtr['last_date_label'] }}
        </p>

        <p class="dtr-scroll-hint d-md-none" id="dtr-scroll-hint">
            <i class="ti ti-arrows-horizontal" aria-hidden="true"></i>
            Swipe horizontally to view all columns
        </p>

        <div class="table-responsive dtr-preview-table" tabindex="0" role="region"
             aria-label="Monthly DTR table" aria-describedby="dtr-scroll-hint">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Total Hrs.</th>
                        <th scope="col">Work Arrangement</th>
                        <th scope="col">Attendance Rendered</th>
                        <th scope="col">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dtr['rows'] as $row)
                        <tr>
                            <th class="text-nowrap fw-normal" scope="row">{{ $row['date_label'] }}</th>
                            <td>{{ $row['total_hours'] }}</td>
                            <td>
                                @if ($row['has_legacy_arrangement'])
                                    <span class="badge arrangement-badge">Not recorded</span>
                                @elseif ($row['work_arrangement'] !== null)
                                    <span class="badge arrangement-badge">{{ $row['work_arrangement'] }}</span>
                                @endif
                            </td>
                            <td>{{ $row['attendance_rendered'] }}</td>
                            <td>{{ $row['remarks'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
