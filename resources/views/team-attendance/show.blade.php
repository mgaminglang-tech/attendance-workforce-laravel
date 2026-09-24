@extends('layouts.app')

@section('title', 'Team Attendance | '.config('app.name'))

@section('content')
    <div class="container-xl page-shell team-attendance-page" data-team-attendance data-status-url="{{ $statusUrl }}">
        <header class="team-thread-header">
            <div>
                <span class="eyebrow">{{ $accessLabel }}</span>
                <h1>{{ $teamAttendance['department']['name'] }} Team</h1>
                <p class="team-summary-line">
                    <span><strong data-summary="total">{{ $teamAttendance['summary']['total'] }}</strong> members</span>
                    <span><strong data-summary="working">{{ $teamAttendance['summary']['working'] }}</strong> working</span>
                    <span><strong data-summary="completed">{{ $teamAttendance['summary']['completed'] }}</strong> completed</span>
                    <span><strong data-summary="not_clocked_in">{{ $teamAttendance['summary']['not_clocked_in'] }}</strong> not clocked in</span>
                </p>
            </div>
            <div class="team-refresh-status" aria-live="polite">
                <span><i class="ti ti-refresh" aria-hidden="true"></i> Updated <span data-last-updated>{{ $teamAttendance['last_updated'] }}</span></span>
                <span class="text-danger d-none" data-refresh-error>Refresh unavailable. Showing the last successful update.</span>
            </div>
        </header>

        <section class="team-thread" aria-labelledby="activity-heading">
            <div class="team-thread-heading">
                <div>
                    <p class="eyebrow mb-1">Live department activity</p>
                    <h2 id="activity-heading">Attendance thread</h2>
                </div>
                <span class="live-indicator"><span aria-hidden="true"></span>Auto-refreshes</span>
            </div>

            <ol class="attendance-feed" data-team-activity aria-label="Attendance activity">
                @forelse ($teamAttendance['activity'] as $event)
                    @if ($loop->first || $event['date_label'] !== $teamAttendance['activity'][$loop->index - 1]['date_label'])
                        <li class="feed-date-separator" aria-label="{{ $event['date_label'] }}"><span>{{ $event['date_label'] }}</span></li>
                    @endif
                    <li class="attendance-event {{ $event['event'] === 'Not Clocked In' ? 'attendance-event-muted' : '' }}">
                        <span class="avatar attendance-avatar" aria-hidden="true">{{ $event['employee_initials'] }}</span>
                        <div class="attendance-event-content">
                            <div class="attendance-event-main">
                                <span class="attendance-event-name">{{ $event['employee_name'] }}</span>
                                @if ($event['is_hr_representative'])
                                    <span class="hr-badge" aria-label="HR Representative" title="HR Representative">HR</span>
                                @endif
                                @if ($event['event_time'] !== null)
                                    <time datetime="{{ $event['occurred_at'] }}">{{ $event['event_time'] }}</time>
                                @endif
                            </div>
                            <p class="attendance-event-action mb-0">{{ $event['event'] }}</p>
                            @if ($event['work_arrangement'] !== null)
                                <div class="attendance-event-meta">
                                    <span class="arrangement-chip">{{ $event['work_arrangement'] }}</span>
                                </div>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="feed-empty-state">No active employees are assigned to this department.</li>
                @endforelse
            </ol>
        </section>

        <script type="application/json" data-team-initial-payload>@json(['activity' => $teamAttendance['activity']])</script>
    </div>
@endsection
