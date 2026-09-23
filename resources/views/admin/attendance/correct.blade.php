@extends('layouts.app')

@section('title', 'Correct Attendance | '.config('app.name'))

@section('content')
    <div class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="mb-4">
                    <span class="badge text-bg-warning mb-2">Administrative correction</span>
                    <h1 class="h2 mb-1">Correct attendance session</h1>
                    <p class="text-body-secondary mb-0">Every saved correction is permanently attributed and recorded with before-and-after values.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <p class="detail-label mb-1">Employee</p>
                                <p class="fw-semibold mb-0">{{ $attendanceSession->employee->user->name }}</p>
                                <p class="small text-body-secondary mb-0">{{ $attendanceSession->employee->employee_number }}</p>
                            </div>
                            <div class="text-end">
                                <p class="detail-label mb-1">Current work date</p>
                                <p class="mb-0">{{ $attendanceSession->work_date->format('M j, Y') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-warning shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('admin.attendance.correction.update', $attendanceSession) }}"
                              data-submit-once data-confirm-message="Save this administrative correction? The audit entry cannot be edited or deleted.">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="time_in_at">Corrected Time In</label>
                                    <input class="form-control @error('time_in_at') is-invalid @enderror" id="time_in_at"
                                           name="time_in_at" type="datetime-local" step="1" required
                                           value="{{ old('time_in_at', $attendanceSession->time_in_at->format('Y-m-d\TH:i:s')) }}">
                                    <div class="form-text">Asia/Manila time. Work date is derived from this value.</div>
                                    @error('time_in_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="time_out_at">Corrected Time Out</label>
                                    <input class="form-control @error('time_out_at') is-invalid @enderror" id="time_out_at"
                                           name="time_out_at" type="datetime-local" step="1"
                                           value="{{ old('time_out_at', $attendanceSession->time_out_at?->format('Y-m-d\TH:i:s')) }}">
                                    <div class="form-text">Leave blank only when the attendance session should remain open.</div>
                                    @error('time_out_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="reason">Correction reason</label>
                                    <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason"
                                              rows="4" maxlength="1000" minlength="10" required
                                              placeholder="Describe what was verified and why this correction is necessary.">{{ old('reason') }}</textarea>
                                    <div class="form-text">Required. Provide enough context for future review.</div>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                                <a class="btn btn-outline-secondary" href="{{ route('admin.attendance.show', $attendanceSession) }}">Cancel</a>
                                <button class="btn btn-warning" type="submit" data-submitting-text="Saving correction…">Save correction</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
