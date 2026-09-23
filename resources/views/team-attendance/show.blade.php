@extends('layouts.app')

@section('title', 'Team Attendance | '.config('app.name'))

@section('content')
    <div class="container py-4 py-md-5" data-team-attendance data-status-url="{{ $statusUrl }}">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
                <span class="badge text-bg-secondary mb-2">{{ $accessLabel }}</span>
                <h1 class="h2 mb-1">{{ $teamAttendance['department']['name'] }} Team Attendance</h1>
                <p class="text-body-secondary mb-0">Operational attendance visibility for the current Manila work date.</p>
            </div>
            <div class="text-md-end">
                <p class="small text-body-secondary mb-1">Last updated</p>
                <p class="small fw-semibold mb-0" data-last-updated>{{ $teamAttendance['last_updated'] }}</p>
                <p class="small text-danger mb-0 d-none" data-refresh-error>Refresh unavailable. Displaying the last successful update.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="detail-label mb-1">Team members</p><p class="h3 mb-0" data-summary="total">{{ $teamAttendance['summary']['total'] }}</p></div></div></div>
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="detail-label mb-1">Working</p><p class="h3 text-success mb-0" data-summary="working">{{ $teamAttendance['summary']['working'] }}</p></div></div></div>
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="detail-label mb-1">Completed today</p><p class="h3 text-primary mb-0" data-summary="completed">{{ $teamAttendance['summary']['completed'] }}</p></div></div></div>
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="detail-label mb-1">Not clocked in</p><p class="h3 text-secondary mb-0" data-summary="not_clocked_in">{{ $teamAttendance['summary']['not_clocked_in'] }}</p></div></div></div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Employee</th><th>Status</th><th>Work date</th><th>Time In</th><th>Time Out</th></tr></thead>
                    <tbody data-team-members>
                        @forelse ($teamAttendance['members'] as $member)
                            <tr>
                                <td><span class="fw-semibold">{{ $member['employee_name'] }}</span><br><span class="small text-body-secondary">{{ $member['employee_number'] }}</span></td>
                                <td><span class="badge status-badge text-bg-{{ $member['status'] === 'Working' ? 'success' : ($member['status'] === 'Completed' ? 'primary' : 'secondary') }}">{{ $member['status'] }}</span></td>
                                <td>{{ $member['work_date'] ?? '—' }}</td>
                                <td>{{ $member['time_in'] ?? '—' }}</td>
                                <td>{{ $member['time_out'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-5" colspan="5">No active employees are assigned to this department.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
