@extends('layouts.app')

@section('title', 'Attendance | '.config('app.name'))

@section('content')
    <div class="container py-4 py-md-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <span class="badge text-bg-secondary mb-2">Employee attendance</span>
                <h1 class="h2 mb-1">Timekeeping</h1>
                <p class="text-body-secondary mb-0">Your attendance is recorded using authoritative server time.</p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('employee.attendance.history') }}">View attendance history</a>
        </div>

        @if ($errors->has('attendance'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('attendance') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <p class="detail-label mb-2">Current time in Asia/Manila</p>
                        <p class="attendance-clock mb-1" data-manila-clock>Loading current time…</p>
                        <p class="small text-body-secondary mb-0">Display only. The server records every Time In and Time Out.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                            <div>
                                <p class="detail-label mb-1">Attendance status</p>
                                @if ($currentSession?->time_out_at === null && $currentSession !== null)
                                    <h2 class="h4 mb-0">Currently working</h2>
                                @elseif ($currentSession !== null)
                                    <h2 class="h4 mb-0">Completed for today</h2>
                                @else
                                    <h2 class="h4 mb-0">Not timed in</h2>
                                @endif
                            </div>
                            <span class="badge text-bg-{{ $currentSession?->time_out_at === null && $currentSession !== null ? 'success' : 'secondary' }} status-badge">
                                {{ $currentSession?->time_out_at === null && $currentSession !== null ? 'Open' : ($currentSession !== null ? 'Completed' : 'Ready') }}
                            </span>
                        </div>

                        @if ($currentSession !== null)
                            <dl class="row mb-4">
                                <dt class="col-sm-4 text-body-secondary">Work date</dt>
                                <dd class="col-sm-8">{{ $currentSession->work_date->format('M j, Y') }}</dd>
                                <dt class="col-sm-4 text-body-secondary">Time In</dt>
                                <dd class="col-sm-8">{{ $currentSession->time_in_at->format('M j, Y g:i:s A') }}</dd>
                                <dt class="col-sm-4 text-body-secondary">Time Out</dt>
                                <dd class="col-sm-8 mb-0">{{ $currentSession->time_out_at?->format('M j, Y g:i:s A') ?? 'Still working' }}</dd>
                            </dl>
                        @else
                            <p class="text-body-secondary">No attendance has been recorded for {{ $workDate }}.</p>
                        @endif

                        @if ($employee->employment_status !== \App\Enums\EmploymentStatus::Active)
                            <div class="alert alert-warning mb-0" role="status">
                                Your employment status does not permit attendance actions. Contact an administrator if this is unexpected.
                            </div>
                        @elseif ($currentSession?->time_out_at === null && $currentSession !== null)
                            <form method="POST" action="{{ route('employee.attendance.time-out') }}" data-submit-once>
                                @csrf
                                <button class="btn btn-danger btn-lg" type="submit" data-submitting-text="Recording Time Out…">Time Out</button>
                            </form>
                        @elseif ($currentSession === null)
                            <form method="POST" action="{{ route('employee.attendance.time-in') }}" data-submit-once>
                                @csrf
                                <button class="btn btn-workforce btn-lg" type="submit" data-submitting-text="Recording Time In…">Time In</button>
                            </form>
                        @else
                            <p class="text-success fw-semibold mb-0">Your attendance for this work date is complete.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
