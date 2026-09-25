@extends('layouts.app')

@section('title', 'Attendance History | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
            <div>
                <span class="eyebrow">Employee attendance</span>
                <h1 class="h2 mb-1">Attendance history</h1>
                <p class="text-body-secondary mb-0">Your attendance sessions and recorded leave, newest date first.</p>
            </div>
            <a class="btn btn-sm btn-outline-secondary align-self-start" href="{{ route('employee.attendance.index') }}">
                <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Back to timekeeping
            </a>
        </header>

        <div class="card surface-card">
            <div class="attendance-history-mobile d-md-none">
                @forelse ($historyRecords as $record)
                    <article class="attendance-history-record" data-attendance-mobile-record>
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <p class="attendance-history-date mb-1">{{ $record['date']->format('M j, Y') }}</p>
                                @if ($record['type'] === 'attendance')
                                    <p class="attendance-history-times mb-0">
                                        {{ $record['time_in_at']->format('g:i A') }}
                                        <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                        {{ $record['time_out_at']?->format('g:i A') ?? 'Still working' }}
                                    </p>
                                @endif
                            </div>
                            <span class="badge status-badge {{ $record['status'] === 'On Leave' ? 'status-badge-leave' : ($record['status'] === 'Working' ? 'status-badge-working' : 'status-badge-completed') }}">
                                {{ $record['status'] }}
                            </span>
                        </div>
                        @if ($record['type'] === 'attendance')
                            <div class="attendance-history-meta">
                                <span class="badge arrangement-badge">{{ $record['work_arrangement'] ?? 'Not recorded' }}</span>
                                @if ($record['worked_minutes'] !== null)
                                    <span class="attendance-history-duration">Duration: {{ intdiv($record['worked_minutes'], 60) }}h {{ $record['worked_minutes'] % 60 }}m</span>
                                @endif
                            </div>
                        @endif
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
                        @forelse ($historyRecords as $record)
                            <tr>
                                <td class="fw-semibold">{{ $record['date']->format('M j, Y') }}</td>
                                <td>{{ $record['time_in_at']?->format('M j, Y g:i:s A') ?? '—' }}</td>
                                <td>{{ $record['type'] === 'leave' ? '—' : ($record['time_out_at']?->format('M j, Y g:i:s A') ?? 'Still working') }}</td>
                                <td>
                                    @if ($record['work_arrangement'] !== null)
                                        <span class="badge arrangement-badge">{{ $record['work_arrangement'] }}</span>
                                    @elseif ($record['type'] === 'attendance')
                                        <span class="badge arrangement-badge">Not recorded</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($record['type'] === 'leave')
                                        <span class="badge status-badge status-badge-leave">On Leave</span>
                                    @elseif ($record['worked_minutes'] === null)
                                        <span class="badge status-badge status-badge-working">Working</span>
                                    @else
                                        {{ intdiv($record['worked_minutes'], 60) }}h {{ $record['worked_minutes'] % 60 }}m
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
            @if ($historyRecords->hasPages())
                <div class="card-footer bg-white py-3">
                    {{ $historyRecords->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
