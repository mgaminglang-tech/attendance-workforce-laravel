@extends('layouts.app')

@section('title', 'Attendance | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
            <div>
                <span class="eyebrow">Employee attendance</span>
                <h1 class="h2 mb-1">Timekeeping</h1>
                <p class="text-body-secondary mb-0">Your attendance is recorded using authoritative server time.</p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('employee.attendance.history') }}">View history</a>
        </header>

        @if ($errors->has('attendance'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('attendance') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card surface-card h-100">
                    <div class="card-body p-4">
                        <p class="detail-label mb-2">Current time in Asia/Manila</p>
                        <p class="attendance-clock mb-1" data-manila-clock>Loading current time…</p>
                        <p class="small text-body-secondary mb-0">Display only. The server records every Time In and Time Out.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                @include('employee.attendance._status-panel')
            </div>
        </div>
    </div>
@endsection
