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

    <div class="modal fade" id="record-leave-modal" tabindex="-1" aria-labelledby="record-leave-modal-title" aria-hidden="true"
         data-open-modal-on-load="{{ $errors->hasAny(['from_date', 'to_date', 'leave_record']) ? 'true' : 'false' }}">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('employee.attendance.leave.store') }}" data-submit-once>
                    @csrf
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow mb-1">Employee leave</p>
                            <h2 class="modal-title fs-5" id="record-leave-modal-title">Record leave dates</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-body-secondary">Every calendar date in the range will be recorded, including weekends and holidays.</p>

                        @error('leave_record')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="leave-from-date">First day</label>
                                <input class="form-control @error('from_date') is-invalid @enderror" id="leave-from-date"
                                       name="from_date" type="date" value="{{ old('from_date', $workDate) }}" required
                                       @error('from_date') aria-describedby="leave-from-date-error" @enderror>
                                @error('from_date')
                                    <div class="invalid-feedback" id="leave-from-date-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="leave-to-date">Last day</label>
                                <input class="form-control @error('to_date') is-invalid @enderror" id="leave-to-date"
                                       name="to_date" type="date" value="{{ old('to_date', old('from_date', $workDate)) }}" required
                                       @error('to_date') aria-describedby="leave-to-date-error" @enderror>
                                @error('to_date')
                                    <div class="invalid-feedback" id="leave-to-date-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-workforce" data-submitting-text="Recording Leave…">Record Leave</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
