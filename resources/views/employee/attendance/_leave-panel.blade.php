<section class="leave-panel" aria-labelledby="leave-panel-heading">
    <div class="leave-panel-header">
        <div>
            <p class="eyebrow mb-1">Leave</p>
            <h2 class="h4 mb-1" id="leave-panel-heading">Record time away</h2>
            <p class="text-body-secondary mb-0">Add one day or an inclusive date range.</p>
        </div>
        <button class="btn btn-outline-secondary leave-open-button" type="button"
                data-bs-toggle="modal" data-bs-target="#record-leave-modal">
            <i class="ti ti-calendar-plus me-2" aria-hidden="true"></i>Record Leave
        </button>
    </div>

    @if ($upcomingLeaveDays->isNotEmpty())
        <div class="leave-upcoming" aria-labelledby="upcoming-leave-heading">
            <h3 id="upcoming-leave-heading">Current and upcoming leave</h3>
            <div class="leave-day-list">
                @foreach ($upcomingLeaveDays as $leaveDay)
                    <div class="leave-day-row">
                        <div>
                            <strong>{{ $leaveDay->leave_date->isToday() ? 'Today' : $leaveDay->leave_date->format('D, M j') }}</strong>
                            <span>{{ $leaveDay->leave_date->format('Y') }}</span>
                        </div>
                        <form method="POST" action="{{ route('employee.attendance.leave.destroy', $leaveDay) }}"
                              data-submit-once data-confirm-message="Remove this leave record?">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-secondary" type="submit" data-submitting-text="Removing…">
                                Remove<span class="visually-hidden"> leave for {{ $leaveDay->leave_date->format('F j, Y') }}</span>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
