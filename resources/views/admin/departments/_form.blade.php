@php($department = $department ?? null)

<div class="mb-3">
    <label class="form-label" for="name">Department name</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $department?->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="code">Code</label>
    <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $department?->code) }}" required>
    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="form-check mb-4">
    <input name="is_active" type="hidden" value="0">
    <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $department?->is_active ?? true))>
    <label class="form-check-label" for="is_active">Active and available for new assignments</label>
</div>
<div class="d-flex gap-2">
    <button class="btn btn-workforce" type="submit">{{ $department ? 'Save changes' : 'Create department' }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.departments.index') }}">Cancel</a>
</div>
