<section class="attendance-console" aria-labelledby="attendance-status-heading">
    <div class="attendance-console-header">
        <div>
            <p class="eyebrow mb-1">Current attendance</p>
            @if ($currentSession?->time_out_at === null && $currentSession !== null)
                <h2 id="attendance-status-heading">Currently working</h2>
                <p>Your attendance session is open.</p>
            @elseif ($currentSession !== null)
                <h2 id="attendance-status-heading">Completed for today</h2>
                <p>Your attendance for this work date is complete.</p>
            @else
                <h2 id="attendance-status-heading">Not clocked in</h2>
                <p>Choose your work arrangement, then record Time In.</p>
            @endif
        </div>
        <span class="status-indicator {{ $currentSession?->time_out_at === null && $currentSession !== null ? 'status-indicator-working' : ($currentSession !== null ? 'status-indicator-completed' : 'status-indicator-neutral') }}">
            <i class="ti {{ $currentSession?->time_out_at === null && $currentSession !== null ? 'ti-clock-play' : ($currentSession !== null ? 'ti-circle-check' : 'ti-clock-off') }}" aria-hidden="true"></i>
            {{ $currentSession?->time_out_at === null && $currentSession !== null ? 'Working' : ($currentSession !== null ? 'Completed' : 'Not Clocked In') }}
        </span>
    </div>

    @if ($currentSession !== null)
        <div class="attendance-session" aria-labelledby="today-session-heading">
            <h3 id="today-session-heading">Today’s session</h3>
            <dl class="attendance-details">
                <div><dt>Time In</dt><dd><time datetime="{{ $currentSession->time_in_at->toIso8601String() }}" title="{{ $currentSession->time_in_at->format('M j, Y g:i:s A') }}">{{ $currentSession->time_in_at->format('g:i A') }}</time></dd></div>
                <div><dt>Time Out</dt><dd>@if ($currentSession->time_out_at)<time datetime="{{ $currentSession->time_out_at->toIso8601String() }}" title="{{ $currentSession->time_out_at->format('M j, Y g:i:s A') }}">{{ $currentSession->time_out_at->format('g:i A') }}</time>@else Still working @endif</dd></div>
                <div><dt>Work Arrangement</dt><dd>{{ $currentSession->work_arrangement?->label() ?? 'Not recorded' }}</dd></div>
                @if ($netWorkedMinutes !== null)
                    <div><dt>Net worked</dt><dd>{{ number_format($netWorkedMinutes / 60, 2) }} hrs</dd></div>
                @endif
            </dl>
            <p class="attendance-session-date"><i class="ti ti-calendar" aria-hidden="true"></i>{{ $currentSession->work_date->format('F j, Y') }}</p>
        </div>
    @endif

    <div class="attendance-console-action">
        @if ($employee->employment_status !== \App\Enums\EmploymentStatus::Active)
            <div class="alert alert-warning mb-0" role="status">
                Your employment status does not permit attendance actions. Contact an administrator if this is unexpected.
            </div>
        @elseif ($currentSession?->time_out_at === null && $currentSession !== null)
            <form method="POST" action="{{ route('employee.attendance.time-out') }}" data-submit-once>
                @csrf
                <button class="btn btn-workforce btn-lg attendance-action" type="submit" data-submitting-text="Recording Time Out…">
                    <i class="ti ti-clock-stop me-2" aria-hidden="true"></i>Time Out
                </button>
            </form>
        @elseif ($currentSession === null)
            <form method="POST" action="{{ route('employee.attendance.time-in') }}" data-submit-once>
                @csrf
                <fieldset>
                    <legend>Work arrangement <span class="text-danger" aria-hidden="true">*</span></legend>
                    <div class="work-arrangement-grid">
                        @foreach ($workArrangements as $workArrangement)
                            <div class="col-12 col-sm-4 work-arrangement-option">
                                <input class="btn-check @error('work_arrangement') is-invalid @enderror"
                                       id="work-arrangement-{{ $workArrangement->value }}"
                                       name="work_arrangement" type="radio" value="{{ $workArrangement->value }}"
                                       autocomplete="off" required
                                       @if ($errors->has('work_arrangement')) aria-describedby="work-arrangement-error" @endif
                                       @checked(old('work_arrangement') === $workArrangement->value)>
                                <label for="work-arrangement-{{ $workArrangement->value }}">
                                    <i class="ti {{ match ($workArrangement) { \App\Enums\WorkArrangement::WorkFromHome => 'ti-home', \App\Enums\WorkArrangement::OfficeBased => 'ti-building', \App\Enums\WorkArrangement::FieldBased => 'ti-map-pin' } }}" aria-hidden="true"></i>
                                    <span>{{ $workArrangement->label() }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('work_arrangement')
                        <p class="text-danger small mt-2 mb-0" id="work-arrangement-error">{{ $message }}</p>
                    @enderror
                </fieldset>
                <button class="btn btn-workforce btn-lg attendance-action" type="submit" data-submitting-text="Recording Time In…">
                    <i class="ti ti-clock-play me-2" aria-hidden="true"></i>Time In
                </button>
            </form>
        @endif
    </div>
</section>
