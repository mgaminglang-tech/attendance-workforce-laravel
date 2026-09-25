@extends('layouts.app')

@section('title', 'Attendance Details | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
            <div>
                <span class="eyebrow">Current Attendance</span>
                <h1 class="h2 mb-1">{{ $attendanceSession->employee->user->name }}</h1>
                <p class="text-body-secondary mb-0">{{ $attendanceSession->employee->employee_number }}</p>
            </div>
            <div class="page-header-actions d-flex flex-column flex-sm-row gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('admin.attendance.index') }}">Back to attendance</a>
                <a class="btn btn-warning" href="{{ route('admin.attendance.correction.edit', $attendanceSession) }}">Correct attendance</a>
            </div>
        </header>

        <div class="card surface-card mb-4">
            <div class="card-body p-4">
                @php($workedMinutes = $attendanceSession->workedMinutes())
                <div class="row g-4">
                    <div class="col-md-4"><p class="detail-label mb-1">Department</p><p class="mb-0">{{ $attendanceSession->employee->department?->name ?? 'Unassigned' }}</p></div>
                    <div class="col-md-4"><p class="detail-label mb-1">Work date</p><p class="mb-0">{{ $attendanceSession->work_date->format('M j, Y') }}</p></div>
                    <div class="col-md-4"><p class="detail-label mb-1">Status</p><p class="mb-0"><span class="badge status-badge {{ $attendanceSession->time_out_at === null ? 'status-badge-working' : 'status-badge-completed' }}">{{ $attendanceSession->time_out_at === null ? 'Working' : 'Completed' }}</span></p></div>
                    <div class="col-md-4"><p class="detail-label mb-1">Time In</p><p class="mb-0">{{ $attendanceSession->time_in_at->format('M j, Y g:i:s A') }}</p></div>
                    <div class="col-md-4"><p class="detail-label mb-1">Time Out</p><p class="mb-0">{{ $attendanceSession->time_out_at?->format('M j, Y g:i:s A') ?? 'Still working' }}</p></div>
                    <div class="col-md-4"><p class="detail-label mb-1">Work Arrangement</p><p class="mb-0">{{ $attendanceSession->work_arrangement?->label() ?? 'Not recorded' }}</p></div>
                    <div class="col-md-4"><p class="detail-label mb-1">Duration</p><p class="mb-0">{{ $workedMinutes === null ? 'Open' : intdiv($workedMinutes, 60).'h '.($workedMinutes % 60).'m' }}</p></div>
                    <div class="col-md-4"><p class="detail-label mb-1">Created</p><p class="mb-0">{{ $attendanceSession->created_at->format('M j, Y g:i:s A') }}</p></div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">Correction history</h2>
            <span class="badge status-badge status-badge-neutral">{{ $attendanceSession->adjustments->count() }} recorded</span>
        </div>

        @forelse ($attendanceSession->adjustments as $adjustment)
            <article class="card surface-card mb-3">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                        <div>
                            <p class="fw-semibold mb-0">{{ $adjustment->administrator->name }}</p>
                            <p class="small text-body-secondary mb-0">Administrator</p>
                        </div>
                        <time class="small text-body-secondary" datetime="{{ $adjustment->corrected_at->toIso8601String() }}">
                            {{ $adjustment->corrected_at->format('M j, Y g:i:s A') }}
                        </time>
                    </div>
                    <p class="mb-3"><span class="fw-semibold">Reason:</span> {{ $adjustment->reason }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <p class="detail-label mb-2">Before</p>
                                <p class="small mb-1">Work date: {{ $adjustment->previous_work_date->format('M j, Y') }}</p>
                                <p class="small mb-1">Time In: {{ $adjustment->previous_time_in_at->format('M j, Y g:i:s A') }}</p>
                                <p class="small mb-0">Time Out: {{ $adjustment->previous_time_out_at?->format('M j, Y g:i:s A') ?? 'Open' }}</p>
                                <p class="small mb-0">Work Arrangement: {{ $adjustment->before_work_arrangement?->label() ?? 'Not recorded' }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border border-primary-subtle bg-primary-subtle rounded p-3 h-100">
                                <p class="detail-label mb-2">After</p>
                                <p class="small mb-1">Work date: {{ $adjustment->corrected_work_date->format('M j, Y') }}</p>
                                <p class="small mb-1">Time In: {{ $adjustment->corrected_time_in_at->format('M j, Y g:i:s A') }}</p>
                                <p class="small mb-0">Time Out: {{ $adjustment->corrected_time_out_at?->format('M j, Y g:i:s A') ?? 'Open' }}</p>
                                <p class="small mb-0">Work Arrangement: {{ $adjustment->after_work_arrangement?->label() ?? 'Not recorded' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="card surface-card">
                <div class="card-body empty-state">No corrections have been recorded for this attendance session.</div>
            </div>
        @endforelse
    </div>
@endsection
