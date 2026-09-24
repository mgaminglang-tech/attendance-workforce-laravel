@extends('layouts.app')

@section('title', 'Global Admin Dashboard | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
            <div>
                <span class="eyebrow">Global Admin</span>
                <h1 class="h2 mb-1">Global Admin Dashboard</h1>
                <p class="text-body-secondary mb-0">Workforce activity for the current Manila work date, {{ $workDate }}.</p>
            </div>
            <a class="btn btn-workforce" href="{{ route('admin.employees.create') }}">Add Employee</a>
        </header>

        <section class="row g-3 mb-4" aria-label="Workforce summary">
            @foreach ([
                ['label' => 'Total Employees', 'value' => $summary['employees'], 'class' => ''],
                ['label' => 'Working Now', 'value' => $summary['working'], 'class' => 'text-success'],
                ['label' => 'Completed Today', 'value' => $summary['completed_today'], 'class' => ''],
                ['label' => 'Active Departments', 'value' => $summary['active_departments'], 'class' => ''],
            ] as $metric)
                <div class="col-6 col-xl-3">
                    <div class="card metric-card h-100">
                        <div class="card-body">
                            <p class="detail-label mb-1">{{ $metric['label'] }}</p>
                            <p class="metric-value {{ $metric['class'] }}">{{ $metric['value'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="card surface-card h-100" aria-labelledby="recent-attendance-heading">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center gap-3 py-3 px-4">
                        <div>
                            <h2 class="h5 mb-0" id="recent-attendance-heading">Recent attendance</h2>
                            <p class="small text-body-secondary mb-0">Latest recorded workforce sessions.</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.attendance.index') }}">View all</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th scope="col">Employee</th><th scope="col">Department</th><th scope="col">Work date</th><th scope="col">Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($recentSessions as $session)
                                    <tr>
                                        <td><span class="fw-semibold">{{ $session->employee->user->name }}</span><span class="d-block small text-body-secondary">{{ $session->employee->employee_number }}</span></td>
                                        <td>{{ $session->employee->department?->name ?? 'Unassigned' }}</td>
                                        <td>{{ $session->work_date->format('M j, Y') }}</td>
                                        <td><span class="badge status-badge {{ $session->time_out_at === null ? 'status-badge-working' : 'status-badge-completed' }}">{{ $session->time_out_at === null ? 'Working' : 'Completed' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td class="empty-state" colspan="4">No attendance records found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-xl-4">
                <section class="card surface-card h-100" aria-labelledby="quick-actions-heading">
                    <div class="card-body p-4">
                        <p class="detail-label mb-1">Shortcuts</p>
                        <h2 class="h5 mb-3" id="quick-actions-heading">Quick actions</h2>
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-primary text-start" href="{{ route('admin.employees.index') }}">Manage Employees</a>
                            <a class="btn btn-outline-primary text-start" href="{{ route('admin.departments.index') }}">Manage Departments</a>
                            <a class="btn btn-outline-primary text-start" href="{{ route('admin.team-attendance.index') }}">Team Attendance</a>
                            <a class="btn btn-outline-primary text-start" href="{{ route('admin.dtr.index') }}">Employee DTR</a>
                            <a class="btn btn-outline-primary text-start" href="{{ route('admin.reports.attendance.index') }}">Attendance Reports</a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
