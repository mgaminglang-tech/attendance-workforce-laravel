@extends('layouts.app')

@section('title', 'Attendance | '.config('app.name'))

@section('content')
    <div class="container-xl page-shell attendance-page">
        <header class="page-header">
            <div>
                <span class="eyebrow">Employee attendance</span>
                <h1 class="h2 mb-1">Timekeeping</h1>
                <p class="text-body-secondary mb-0">Your attendance is recorded using authoritative server time.</p>
            </div>
        </header>

        @if ($errors->has('attendance'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('attendance') }}</div>
        @endif

        @if ($errors->has('leave_remove'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('leave_remove') }}</div>
        @endif

        <div class="attendance-workspace">
            <div class="attendance-primary-column">
                @include('employee.attendance._status-panel')

                @include('employee.attendance._leave-panel')
            </div>

            <div>
                <section class="workforce-time-panel" aria-labelledby="server-time-heading">
                    <p class="detail-label mb-2" id="server-time-heading">Current server time</p>
                    <time class="attendance-clock" data-manila-clock-time>--:-- --</time>
                    <p class="server-date" data-manila-clock-date>Loading date…</p>
                    <p class="server-timezone">Asia/Manila</p>
                    <p class="server-time-helper">Time In and Time Out use server-recorded timestamps.</p>
                </section>
            </div>
        </div>
    </div>

    @include('employee.attendance._record-leave-modal')
@endsection
