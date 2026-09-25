@extends('layouts.app')

@section('title', 'Attendance Management | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header">
            <span class="eyebrow">Administration</span>
            <h1 class="h2 mb-1">Attendance management</h1>
            <p class="text-body-secondary mb-0">Review workforce attendance and open individual records for controlled corrections.</p>
        </header>

        <div class="card filter-panel mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('admin.attendance.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label class="form-label" for="attendance-search">Employee search</label>
                            <input class="form-control" id="attendance-search" name="search" type="search"
                                   value="{{ request('search') }}" placeholder="Name, email, or employee number">
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label" for="attendance-department">Department</label>
                            <select class="form-select" id="attendance-department" name="department">
                                <option value="">All departments</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) request('department') === (string) $department->id)>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label" for="attendance-state">State</label>
                            <select class="form-select" id="attendance-state" name="state">
                                <option value="">All states</option>
                                <option value="open" @selected(request('state') === 'open')>Open</option>
                                <option value="completed" @selected(request('state') === 'completed')>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label" for="attendance-date-from">Date from</label>
                            <input class="form-control" id="attendance-date-from" name="date_from" type="date" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label" for="attendance-date-to">Date to</label>
                            <input class="form-control" id="attendance-date-to" name="date_to" type="date" value="{{ request('date_to') }}">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-workforce" type="submit">Apply filters</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.attendance.index') }}">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card surface-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">Department</th>
                            <th scope="col">Work date</th>
                            <th scope="col">Time In</th>
                            <th scope="col">Time Out</th>
                            <th scope="col">Work Arrangement</th>
                            <th scope="col">State</th>
                            <th class="text-end" scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendanceSessions as $attendanceSession)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $attendanceSession->employee->user->name }}</div>
                                    <div class="small text-body-secondary">{{ $attendanceSession->employee->employee_number }}</div>
                                </td>
                                <td>{{ $attendanceSession->employee->department?->name ?? 'Unassigned' }}</td>
                                <td>{{ $attendanceSession->work_date->format('M j, Y') }}</td>
                                <td>{{ $attendanceSession->time_in_at->format('M j, Y g:i:s A') }}</td>
                                <td>{{ $attendanceSession->time_out_at?->format('M j, Y g:i:s A') ?? 'Still working' }}</td>
                                <td><span class="badge arrangement-badge">{{ $attendanceSession->work_arrangement?->label() ?? 'Not recorded' }}</span></td>
                                <td>
                                    <span class="badge status-badge {{ $attendanceSession->time_out_at === null ? 'status-badge-working' : 'status-badge-completed' }}">
                                        {{ $attendanceSession->time_out_at === null ? 'Working' : 'Completed' }}
                                    </span>
                                </td>
                                <td class="text-end table-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.attendance.show', $attendanceSession) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="empty-state" colspan="8">No attendance records match the selected filters.</td>
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
