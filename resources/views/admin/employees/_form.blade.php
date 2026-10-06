@php($employee = $employee ?? null)

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="first_name">First Name</label>
        <input class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" autocomplete="given-name" maxlength="255" value="{{ old('first_name', $employee?->first_name) }}" required @error('first_name') aria-invalid="true" aria-describedby="first_name_error" @enderror>
        @error('first_name')<div class="invalid-feedback" id="first_name_error">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="last_name">Last Name</label>
        <input class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" autocomplete="family-name" maxlength="255" value="{{ old('last_name', $employee?->last_name) }}" required @error('last_name') aria-invalid="true" aria-describedby="last_name_error" @enderror>
        @error('last_name')<div class="invalid-feedback" id="last_name_error">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email address</label>
        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $employee?->user->email) }}" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="employee_number">Employee number</label>
        <input class="form-control @error('employee_number') is-invalid @enderror" id="employee_number" name="employee_number" value="{{ old('employee_number', $employee?->employee_number) }}" required>
        @error('employee_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="department_id">Department</label>
        <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
            <option value="">Unassigned</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) old('department_id', $employee?->department_id) === (string) $department->id)>
                    {{ $department->name }}{{ $department->is_active ? '' : ' (Inactive)' }}
                </option>
            @endforeach
        </select>
        @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="job_title">Job title</label>
        <input class="form-control @error('job_title') is-invalid @enderror" id="job_title" name="job_title" value="{{ old('job_title', $employee?->job_title) }}">
        @error('job_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="hired_at">Hired date</label>
        <input class="form-control @error('hired_at') is-invalid @enderror" id="hired_at" name="hired_at" type="date" value="{{ old('hired_at', $employee?->hired_at?->toDateString()) }}">
        @error('hired_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-workforce" type="submit">{{ $employee ? 'Save changes' : 'Create and send invitation' }}</button>
    <a class="btn btn-outline-secondary" href="{{ $employee ? route('admin.employees.show', $employee) : route('admin.employees.index') }}">Cancel</a>
</div>
