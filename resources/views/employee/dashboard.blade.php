@extends('layouts.app')

@section('title', 'Employee Dashboard | '.config('app.name'))

@section('content')
    <div class="container-xl page-shell employee-dashboard">
        <header class="employee-identity">
            <span class="avatar employee-identity-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::of(auth()->user()->name)->squish()->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->implode('') }}</span>
            <div>
                <p class="eyebrow mb-1">{{ now(config('app.timezone'))->format('l, F j') }}</p>
                <h1>{{ auth()->user()->name }}</h1>
                <p>{{ $employee?->department?->name ?? 'Department not assigned' }}@if ($employee?->job_title) · {{ $employee->job_title }}@endif</p>
            </div>
        </header>

        @if ($errors->has('attendance'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('attendance') }}</div>
        @endif

        @if ($hrDepartment !== null)
            <section class="hr-assignment-bar" aria-labelledby="hr-workspace-heading">
                <span class="hr-assignment-icon" aria-hidden="true"><i class="ti ti-shield-check"></i></span>
                <span>
                    <small>Assigned department</small>
                    <strong id="hr-workspace-heading">{{ $hrDepartment->name }}</strong>
                    <span class="hr-assignment-meta">HR reporting responsibility</span>
                </span>
            </section>
        @endif

        <div class="employee-dashboard-grid">
            <div>
                @if ($employee === null)
                    <section class="attendance-console" aria-labelledby="profile-unavailable-heading">
                        <p class="eyebrow mb-1">Attendance unavailable</p>
                        <h2 id="profile-unavailable-heading">Employee profile not found</h2>
                        <p class="mb-0">Contact an administrator to complete your workforce profile.</p>
                    </section>
                @else
                    @include('employee.attendance._status-panel')
                @endif
            </div>

            <section class="recent-attendance" aria-labelledby="recent-personal-attendance-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow mb-1">Personal record</p>
                        <h2 id="recent-personal-attendance-heading">Recent attendance</h2>
                    </div>
                    <a class="section-link" href="{{ route('employee.attendance.history') }}">View all<i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="attendance-preview-list">
                    @forelse ($recentSessions as $session)
                        <div class="attendance-preview-item">
                            <span class="activity-icon {{ $session->time_out_at === null ? 'activity-icon-working' : 'activity-icon-completed' }}" aria-hidden="true"><i class="ti {{ $session->time_out_at === null ? 'ti-clock-play' : 'ti-clock-check' }}"></i></span>
                            <span class="attendance-preview-copy">
                                <strong>{{ $session->work_date->format('M j, Y') }}</strong>
                                <small>{{ $session->work_arrangement?->label() ?? 'Not recorded' }}</small>
                            </span>
                            <span class="status-text">{{ $session->time_out_at === null ? 'Working' : 'Completed' }}</span>
                        </div>
                    @empty
                        <p class="empty-state mb-0">No attendance records yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
