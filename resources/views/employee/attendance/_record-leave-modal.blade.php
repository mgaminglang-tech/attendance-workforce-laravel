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
