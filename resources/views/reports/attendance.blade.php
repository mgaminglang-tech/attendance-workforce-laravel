@extends('layouts.app')

@section('title', 'Attendance Reports | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <span class="eyebrow">{{ $scopeLabel }}</span>
                <h1 class="h2 mb-1">Attendance Reports</h1>
                <p class="text-body-secondary mb-0">
                    @if ($department)
                        Department: <span class="fw-semibold">{{ $department->name }}</span>
                    @else
                        Review canonical attendance across the organization.
                    @endif
                </p>
            </div>
        </header>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Please correct the report filters.</p>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card filter-panel mb-4">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="{{ $reportRoute }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-xl-2">
                            <label class="form-label" for="report-date-from">Date From</label>
                            <input class="form-control" id="report-date-from" name="date_from" type="date"
                                   value="{{ old('date_from', $filters->dateFrom->toDateString()) }}">
                        </div>
                        <div class="col-12 col-md-6 col-xl-2">
                            <label class="form-label" for="report-date-to">Date To</label>
                            <input class="form-control" id="report-date-to" name="date_to" type="date"
                                   value="{{ old('date_to', $filters->dateTo->toDateString()) }}">
                        </div>
                        @if ($isAdmin)
                            <div class="col-12 col-md-6 col-xl-2">
                                <label class="form-label" for="report-department">Department</label>
                                <select class="form-select" id="report-department" name="department">
                                    <option value="">All departments</option>
                                    @foreach ($departments as $departmentOption)
                                        <option value="{{ $departmentOption->id }}" @selected($filters->departmentId === $departmentOption->id)>
                                            {{ $departmentOption->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-12 col-md-6 col-xl-{{ $isAdmin ? '3' : '4' }}">
                            <label class="form-label" for="report-employee">Employee</label>
                            <select class="form-select" id="report-employee" name="employee">
                                <option value="">All employees</option>
                                @foreach ($employees as $employeeOption)
                                    <option value="{{ $employeeOption->id }}" @selected($filters->employeeId === $employeeOption->id)>
                                        {{ $employeeOption->employee_number }} — {{ $employeeOption->user->name }}
                                        @if ($isAdmin && $employeeOption->department) · {{ $employeeOption->department->name }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-xl-2">
                            <label class="form-label" for="report-arrangement">Work Arrangement</label>
                            <select class="form-select" id="report-arrangement" name="work_arrangement">
                                <option value="">All arrangements</option>
                                @foreach (\App\Enums\WorkArrangement::cases() as $arrangement)
                                    <option value="{{ $arrangement->value }}" @selected($filters->workArrangement === $arrangement->value)>
                                        {{ $arrangement->label() }}
                                    </option>
                                @endforeach
                                <option value="not_recorded" @selected($filters->workArrangement === 'not_recorded')>Not recorded</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-xl-1">
                            <label class="form-label" for="report-state">Status</label>
                            <select class="form-select" id="report-state" name="state">
                                <option value="">All</option>
                                <option value="open" @selected($filters->state === 'open')>Open</option>
                                <option value="completed" @selected($filters->state === 'completed')>Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-grid d-sm-flex gap-2 mt-3">
                        <button class="btn btn-workforce" type="submit">Apply Filters</button>
                        <a class="btn btn-outline-secondary" href="{{ $reportRoute }}">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Records</p><p class="metric-value">{{ $summary['records'] }}</p></div></div></div>
            <div class="col-6 col-xl"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Employees</p><p class="metric-value">{{ $summary['unique_employees'] }}</p></div></div></div>
            <div class="col-6 col-xl"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Completed</p><p class="metric-value">{{ $summary['completed'] }}</p></div></div></div>
            <div class="col-6 col-xl"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Open</p><p class="metric-value text-success">{{ $summary['open'] }}</p></div></div></div>
            <div class="col-12 col-xl"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Total Net Hours</p><p class="metric-value">{{ $summary['total_net_hours'] }}</p></div></div></div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between gap-1 mb-3">
            <h2 class="h5 mb-0">Report results</h2>
            <p class="small text-body-secondary mb-0">{{ $filters->dateFrom->format('M j, Y') }} to {{ $filters->dateTo->format('M j, Y') }}</p>
        </div>

        <div class="card surface-card mb-4">
            <div class="table-responsive report-table">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Work Date</th>
                            <th scope="col">Employee</th>
                            <th scope="col">Department</th>
                            <th scope="col">Time In</th>
                            <th scope="col">Time Out</th>
                            <th scope="col">Work Arrangement</th>
                            <th scope="col">Net Hours</th>
                            <th scope="col">Status</th>
                            <th class="text-end" scope="col">DTR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td class="text-nowrap">{{ $session['work_date']->format('M j, Y') }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $session['employee_name'] }}</div>
                                    <div class="small text-body-secondary">{{ $session['employee_number'] }}</div>
                                </td>
                                <td>{{ $session['department_name'] }}</td>
                                <td class="text-nowrap">{{ $session['time_in'] }}</td>
                                <td class="text-nowrap">{{ $session['time_out'] !== '' ? $session['time_out'] : '—' }}</td>
                                <td><span class="badge arrangement-badge">{{ $session['work_arrangement'] }}</span></td>
                                <td>{{ $session['net_hours'] !== '' ? $session['net_hours'] : '—' }}</td>
                                <td>
                                    <span class="badge status-badge {{ $session['status'] === 'Completed' ? 'status-badge-completed' : 'status-badge-working' }}">
                                        {{ $session['status'] }}
                                    </span>
                                </td>
                                <td class="text-end table-actions">
                                    @if ($isAdmin)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.dtr.preview', ['employee_id' => $session['employee_id'], 'month' => $session['work_date']->format('Y-m')]) }}">Open DTR</a>
                                    @else
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.dtr.preview', ['employee' => $session['employee_id'], 'month' => $session['work_date']->format('Y-m')]) }}">Open DTR</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="empty-state" colspan="9">No report results match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sessions->hasPages())
                <div class="card-footer bg-white py-3">{{ $sessions->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>

        <section class="card surface-card department-workspace-card" aria-labelledby="bulk-dtr-heading">
            <div class="card-body p-3 p-md-4">
                <h2 class="h4 mb-1" id="bulk-dtr-heading">Bulk Department DTR Download</h2>
                <p class="text-body-secondary">Download one monthly PDF per department employee in a private ZIP archive.</p>
                <form class="row g-3 align-items-end" method="GET" action="{{ $bulkRoute }}">
                    @if ($isAdmin)
                        <div class="col-12 col-md-6 col-lg-5">
                            <label class="form-label" for="bulk-department">Department</label>
                            <select class="form-select form-select-lg" id="bulk-department" name="department" required>
                                <option value="">Select a department</option>
                                @foreach ($departments as $departmentOption)
                                    <option value="{{ $departmentOption->id }}">{{ $departmentOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="col-12 col-md-6 col-lg-5">
                            <p class="detail-label mb-1">Authorized Department</p>
                            <p class="form-control-plaintext fw-semibold mb-0">{{ $department->name }}</p>
                        </div>
                    @endif
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label" for="bulk-month">Month</label>
                        <input class="form-control form-control-lg" id="bulk-month" name="month" type="month" required value="{{ $selectedMonth }}">
                    </div>
                    <div class="col-12 col-lg-auto">
                        <button class="btn btn-workforce btn-lg w-100" type="submit">Download Department DTRs</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
