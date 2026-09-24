@extends('layouts.app')

@section('title', 'Employee Dashboard | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header">
            <span class="eyebrow">Employee self-service</span>
            <h1 class="h2 mb-1">Employee Dashboard</h1>
            <p class="text-body-secondary mb-0">Welcome, {{ auth()->user()->name }}. Today is {{ now(config('app.timezone'))->format('l, F j, Y') }}.</p>
        </header>

        @if ($errors->has('attendance'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('attendance') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-xl-8">
                @if ($employee === null)
                    <section class="card surface-card attendance-status-card h-100" aria-labelledby="profile-unavailable-heading">
                        <div class="card-body p-4 p-lg-5">
                            <p class="detail-label mb-1">Attendance unavailable</p>
                            <h2 class="h4" id="profile-unavailable-heading">Employee profile not found</h2>
                            <p class="text-body-secondary mb-0">Contact an administrator to complete your workforce profile.</p>
                        </div>
                    </section>
                @else
                    @include('employee.attendance._status-panel')
                @endif
            </div>
            <div class="col-xl-4">
                <div class="d-grid gap-4">
                    <section class="card surface-card" aria-labelledby="personal-tools-heading">
                        <div class="card-body p-4">
                            <p class="detail-label mb-1">Personal tools</p>
                            <h2 class="h5 mb-3" id="personal-tools-heading">Attendance records</h2>
                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-primary text-start" href="{{ route('employee.attendance.history') }}">View attendance history</a>
                                <a class="btn btn-outline-primary text-start" href="{{ route('employee.dtr.index') }}">Open monthly DTR</a>
                                <a class="btn btn-outline-primary text-start" href="{{ route('team-attendance.index') }}">View team attendance</a>
                            </div>
                        </div>
                    </section>

                    @if ($hrDepartment !== null)
                        <section class="card surface-card department-workspace-card" aria-labelledby="hr-workspace-heading">
                            <div class="card-body p-4">
                                <span class="eyebrow">HR Representative</span>
                                <h2 class="h5 mb-1" id="hr-workspace-heading">{{ $hrDepartment->name }}</h2>
                                <p class="text-body-secondary small mb-3">Your HR access is limited to this assigned department.</p>
                                <div class="d-grid gap-2">
                                    <a class="btn btn-workforce" href="{{ route('hr.team-attendance.index') }}">Department Team Attendance</a>
                                    <a class="btn btn-outline-primary" href="{{ route('hr.reports.attendance.index') }}">Department Reports</a>
                                </div>
                            </div>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
