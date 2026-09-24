@extends('layouts.app')

@section('title', 'Attendance History | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
            <div>
                <span class="eyebrow">Employee attendance</span>
                <h1 class="h2 mb-1">Attendance history</h1>
                <p class="text-body-secondary mb-0">Your attendance sessions, newest work date first.</p>
            </div>
            <a class="btn btn-sm btn-outline-secondary align-self-start" href="{{ route('employee.attendance.index') }}">
                <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Back to timekeeping
            </a>
        </header>

        <div class="card surface-card">
            <div class="attendance-history-mobile d-md-none">
                @forelse ($attendanceSessions as $attendanceSession)
                    @php($workedMinutes = $attendanceSession->workedMinutes())
                    <article class="attendance-history-record" data-attendance-mobile-record>
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <p class="attendance-history-date mb-1">{{ $attendanceSession->work_date->format('M j, Y') }}</p>
                                <p class="attendance-history-times mb-0">
                                    {{ $attendanceSession->time_in_at->format('g:i A') }}
                                    <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                    {{ $attendanceSession->time_out_at?->format('g:i A') ?? 'Still working' }}
                                </p>
                            </div>
                            <span class="badge status-badge {{ $workedMinutes === null ? 'status-badge-working' : 'status-badge-completed' }}">
                                {{ $workedMinutes === null ? 'Working' : 'Completed' }}
                            </span>
                        </div>
                        <div class="attendance-history-meta">
                            <span class="badge arrangement-badge">{{ $attendanceSession->work_arrangement?->label() ?? 'Not recorded' }}</span>
                            @if ($workedMinutes !== null)
                                <span class="attendance-history-duration">Duration: {{ intdiv($workedMinutes, 60) }}h {{ $workedMinutes % 60 }}m</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty-state mb-0">No attendance records found.</p>
                @endforelse
            </div>

            <div class="table-responsive d-none d-md-block" data-attendance-history-table>
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Work date</th>
                            <th scope="col">Time In</th>
                            <th scope="col">Time Out</th>
                            <th scope="col">Work Arrangement</th>
                            <th scope="col">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendanceSessions as $attendanceSession)
                            @php($workedMinutes = $attendanceSession->workedMinutes())
                            <tr>
                                <td class="fw-semibold">{{ $attendanceSession->work_date->format('M j, Y') }}</td>
                                <td>{{ $attendanceSession->time_in_at->format('M j, Y g:i:s A') }}</td>
                                <td>{{ $attendanceSession->time_out_at?->format('M j, Y g:i:s A') ?? 'Still working' }}</td>
                                <td><span class="badge arrangement-badge">{{ $attendanceSession->work_arrangement?->label() ?? 'Not recorded' }}</span></td>
                                <td>
                                    @if ($workedMinutes === null)
                                        <span class="badge status-badge status-badge-working">Working</span>
                                    @else
                                        {{ intdiv($workedMinutes, 60) }}h {{ $workedMinutes % 60 }}m
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="empty-state" colspan="5">No attendance records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($attendanceSessions->hasPages())
                <div class="card-footer bg-white py-3">
                    {{ $attendanceSessions->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
