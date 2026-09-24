@extends('layouts.app')

@section('title', 'Global Admin Dashboard | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell admin-dashboard-page">
        <header class="page-header">
            <div>
                <span class="eyebrow">Global Admin</span>
                <h1>Workforce today</h1>
                <p>Operational attendance for {{ \Carbon\CarbonImmutable::parse($workDate)->format('F j, Y') }}.</p>
            </div>
        </header>

        <section class="workforce-kpis" aria-label="Workforce overview">
            @foreach ([
                ['label' => 'Total employees', 'value' => $summary['employees'], 'icon' => 'ti-users'],
                ['label' => 'Working', 'value' => $summary['working'], 'icon' => 'ti-clock-play'],
                ['label' => 'Completed', 'value' => $summary['completed_today'], 'icon' => 'ti-circle-check'],
                ['label' => 'Not clocked in', 'value' => $summary['not_clocked_in'], 'icon' => 'ti-clock-off'],
            ] as $metric)
                <div class="workforce-kpi">
                    <i class="ti {{ $metric['icon'] }}" aria-hidden="true"></i>
                    <span><strong>{{ $metric['value'] }}</strong>{{ $metric['label'] }}</span>
                </div>
            @endforeach
        </section>

        <div class="dashboard-grid">
            <section class="dashboard-section" aria-labelledby="today-activity-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow mb-1">Organization</p>
                        <h2 id="today-activity-heading">Today’s activity</h2>
                    </div>
                    <a class="section-link" href="{{ route('admin.attendance.index') }}">View attendance<i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="admin-activity-list">
                    @forelse ($recentSessions as $session)
                        <div class="admin-activity-item">
                            <span class="activity-icon {{ $session->time_out_at === null ? 'activity-icon-working' : 'activity-icon-completed' }}" aria-hidden="true">
                                <i class="ti {{ $session->time_out_at === null ? 'ti-clock-play' : 'ti-clock-check' }}"></i>
                            </span>
                            <span class="admin-activity-copy">
                                <strong>{{ $session->employee->user->name }}</strong>
                                <span>{{ $session->time_out_at === null ? 'Timed in' : 'Timed out' }} · {{ $session->employee->department?->name ?? 'Unassigned' }}</span>
                            </span>
                            <time datetime="{{ ($session->time_out_at ?? $session->time_in_at)->toIso8601String() }}">{{ ($session->time_out_at ?? $session->time_in_at)->format('g:i A') }}</time>
                        </div>
                    @empty
                        <p class="empty-state mb-0">No attendance activity recorded for today.</p>
                    @endforelse
                </div>
            </section>

            <section class="dashboard-section" aria-labelledby="department-overview-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow mb-1">Teams</p>
                        <h2 id="department-overview-heading">Department overview</h2>
                    </div>
                </div>
                <div class="department-overview-list">
                    @forelse ($departments as $department)
                        <a class="department-overview-item" href="{{ route('admin.departments.team-attendance.show', $department) }}">
                            <span class="department-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($department->name, 0, 2)) }}</span>
                            <span class="department-overview-copy">
                                <strong>{{ $department->name }}</strong>
                                <small>{{ $department->employees_count }} {{ \Illuminate\Support\Str::plural('member', $department->employees_count) }} · {{ $department->working_count }} working</small>
                            </span>
                            <span class="department-state">{{ $department->working_count > 0 ? 'Active now' : 'No active sessions' }}</span>
                            <i class="ti ti-chevron-right" aria-hidden="true"></i>
                        </a>
                    @empty
                        <p class="empty-state mb-0">No active departments found.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
