<section class="card surface-card attendance-status-card h-100" aria-labelledby="attendance-status-heading">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-3 mb-4">
            <div>
                <p class="detail-label mb-1">Current status</p>
                @if ($currentSession?->time_out_at === null && $currentSession !== null)
                    <h2 class="h3 mb-1" id="attendance-status-heading">Currently working</h2>
                    <p class="text-body-secondary mb-0">Your attendance session is open.</p>
                @elseif ($currentSession !== null)
                    <h2 class="h3 mb-1" id="attendance-status-heading">Completed for today</h2>
                    <p class="text-body-secondary mb-0">Your attendance for this work date is complete.</p>
                @else
                    <h2 class="h3 mb-1" id="attendance-status-heading">Not clocked in</h2>
                    <p class="text-body-secondary mb-0">Select your work arrangement to begin.</p>
                @endif
            </div>
            <span class="badge status-badge {{ $currentSession?->time_out_at === null && $currentSession !== null ? 'status-badge-working' : ($currentSession !== null ? 'status-badge-completed' : 'status-badge-neutral') }}">
                {{ $currentSession?->time_out_at === null && $currentSession !== null ? 'Working' : ($currentSession !== null ? 'Completed' : 'Not Clocked In') }}
            </span>
        </div>

        @if ($currentSession !== null)
            <dl class="attendance-details row g-3 mb-4">
                <div class="col-sm-6">
                    <dt>Work date</dt>
                    <dd>{{ $currentSession->work_date->format('M j, Y') }}</dd>
                </div>
                <div class="col-sm-6">
                    <dt>Work Arrangement</dt>
                    <dd>{{ $currentSession->work_arrangement?->label() ?? 'Not recorded' }}</dd>
                </div>
                <div class="col-sm-6">
                    <dt>Time In</dt>
                    <dd>{{ $currentSession->time_in_at->format('M j, Y g:i:s A') }}</dd>
                </div>
                <div class="col-sm-6">
                    <dt>Time Out</dt>
                    <dd>{{ $currentSession->time_out_at?->format('M j, Y g:i:s A') ?? 'Still working' }}</dd>
                </div>
                @if ($netWorkedMinutes !== null)
                    <div class="col-sm-6">
                        <dt>Net worked duration</dt>
                        <dd>{{ intdiv($netWorkedMinutes, 60) }}h {{ $netWorkedMinutes % 60 }}m</dd>
                    </div>
                @endif
            </dl>
        @endif

        @if ($employee->employment_status !== \App\Enums\EmploymentStatus::Active)
            <div class="alert alert-warning mb-0" role="status">
                Your employment status does not permit attendance actions. Contact an administrator if this is unexpected.
            </div>
        @elseif ($currentSession?->time_out_at === null && $currentSession !== null)
            <form method="POST" action="{{ route('employee.attendance.time-out') }}" data-submit-once>
                @csrf
                <button class="btn btn-danger btn-lg attendance-action" type="submit" data-submitting-text="Recording Time Out…">Time Out</button>
            </form>
        @elseif ($currentSession === null)
            <form method="POST" action="{{ route('employee.attendance.time-in') }}" data-submit-once>
                @csrf
                <fieldset class="mb-4">
                    <legend class="h6 mb-3">Work Arrangement <span class="text-danger" aria-hidden="true">*</span></legend>
                    <div class="row g-2">
                        @foreach ($workArrangements as $workArrangement)
                            <div class="col-12 col-sm-4 work-arrangement-option">
                                <input class="btn-check @error('work_arrangement') is-invalid @enderror"
                                       id="work-arrangement-{{ $workArrangement->value }}"
                                       name="work_arrangement" type="radio" value="{{ $workArrangement->value }}"
                                       autocomplete="off" required
                                       @if ($errors->has('work_arrangement')) aria-describedby="work-arrangement-error" @endif
                                       @checked(old('work_arrangement') === $workArrangement->value)>
                                <label class="btn btn-outline-primary w-100" for="work-arrangement-{{ $workArrangement->value }}">
                                    {{ $workArrangement->label() }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('work_arrangement')
                        <p class="text-danger small mt-2 mb-0" id="work-arrangement-error">{{ $message }}</p>
                    @enderror
                </fieldset>
                <button class="btn btn-workforce btn-lg attendance-action" type="submit" data-submitting-text="Recording Time In…">Time In</button>
            </form>
        @else
            <a class="btn btn-outline-primary" href="{{ route('employee.attendance.history') }}">View attendance history</a>
        @endif
    </div>
</section>
