@extends('layouts.app')

@section('title', 'Edit Department | '.config('app.name'))

@section('content')
    <div class="container py-5">
        <h1 class="h2 mb-4">Edit Department</h1>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.departments.update', $department) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.departments._form')
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1">HR Representative assignment</h2>
                        <p class="text-body-secondary mb-0">Grants read-only access to this department's Team Attendance workspace.</p>
                    </div>
                    <a class="btn btn-outline-primary align-self-start" href="{{ route('admin.departments.team-attendance.show', $department) }}">Open workspace</a>
                </div>

                @if ($department->hrAssignment)
                    <div class="alert alert-light border">
                        <span class="fw-semibold">Current representative:</span>
                        {{ $department->hrAssignment->user->name }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.departments.hr-representative.update', $department) }}" class="row g-3 align-items-end mb-3">
                    @csrf
                    @method('PUT')
                    <div class="col-lg-8">
                        <label class="form-label" for="hr-user-id">HR Representative</label>
                        <select class="form-select @error('user_id') is-invalid @enderror" id="hr-user-id" name="user_id" required>
                            <option value="">Select an active employee</option>
                            @foreach ($eligibleRepresentatives as $representative)
                                <option value="{{ $representative->id }}" @selected((string) old('user_id', $department->hrAssignment?->user_id) === (string) $representative->id)>
                                    {{ $representative->name }} ({{ $representative->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-4">
                        <button class="btn btn-workforce w-100" type="submit">{{ $department->hrAssignment ? 'Replace representative' : 'Assign representative' }}</button>
                    </div>
                </form>

                @if ($department->hrAssignment)
                    <form method="POST" action="{{ route('admin.departments.hr-representative.destroy', $department) }}"
                          data-submit-once data-confirm-message="Remove this HR Representative assignment?">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger" type="submit" data-submitting-text="Removing…">Remove assignment</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
