@extends('layouts.app')

@section('title', 'Team Attendance | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell" data-team-attendance data-status-url="{{ $statusUrl }}">
        <header class="page-header d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <span class="eyebrow">{{ $accessLabel }}</span>
                <h1 class="h2 mb-1">{{ $teamAttendance['department']['name'] }} Team Attendance</h1>
                <p class="text-body-secondary mb-0">Operational attendance visibility for the current Manila work date.</p>
            </div>
            <div class="text-md-end">
                <p class="small text-body-secondary mb-1">Last updated</p>
                <p class="small fw-semibold mb-0" data-last-updated>{{ $teamAttendance['last_updated'] }}</p>
                <p class="small text-danger mb-0 d-none" data-refresh-error>Refresh unavailable. Displaying the last successful update.</p>
            </div>
        </header>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Team members</p><p class="metric-value" data-summary="total">{{ $teamAttendance['summary']['total'] }}</p></div></div></div>
            <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Working</p><p class="metric-value text-success" data-summary="working">{{ $teamAttendance['summary']['working'] }}</p></div></div></div>
            <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Completed today</p><p class="metric-value" data-summary="completed">{{ $teamAttendance['summary']['completed'] }}</p></div></div></div>
            <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><p class="detail-label mb-1">Not clocked in</p><p class="metric-value text-secondary" data-summary="not_clocked_in">{{ $teamAttendance['summary']['not_clocked_in'] }}</p></div></div></div>
        </div>

        <div class="card surface-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">Employee</th><th scope="col">Status</th><th scope="col">Time In</th><th scope="col">Time Out</th><th scope="col">Work Arrangement</th><th scope="col">Work date</th></tr></thead>
                    <tbody data-team-members>
                        @forelse ($teamAttendance['members'] as $member)
                            <tr>
                                <td><span class="fw-semibold">{{ $member['employee_name'] }}</span><br><span class="small text-body-secondary">{{ $member['employee_number'] }}</span></td>
                                <td><span class="badge status-badge {{ $member['status'] === 'Working' ? 'status-badge-working' : ($member['status'] === 'Completed' ? 'status-badge-completed' : 'status-badge-neutral') }}">{{ $member['status'] }}</span></td>
                                <td>{{ $member['time_in'] ?? '—' }}</td>
                                <td>{{ $member['time_out'] ?? '—' }}</td>
                                <td><span class="badge arrangement-badge">{{ $member['work_arrangement'] ?? '—' }}</span></td>
                                <td>{{ $member['work_date'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty-state" colspan="6">No employees found in this department.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
