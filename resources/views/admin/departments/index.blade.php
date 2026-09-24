@extends('layouts.app')

@section('title', 'Departments | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
            <div><h1 class="h2 mb-1">Departments</h1><p class="text-body-secondary mb-0">Manage employee assignment options without deleting history.</p></div>
            <a class="btn btn-workforce" href="{{ route('admin.departments.create') }}">Add Department</a>
        </header>
        <div class="card surface-card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Code</th><th>Status</th><th>Employees</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse ($departments as $department)
                    <tr><td class="fw-semibold">{{ $department->name }}</td><td>{{ $department->code }}</td><td><span class="badge status-badge {{ $department->is_active ? 'status-badge-active' : 'status-badge-inactive' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td><td>{{ $department->employees_count }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.departments.edit', $department) }}">Edit</a></td></tr>
                @empty
                    <tr><td class="empty-state" colspan="5">No departments have been created.</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
        <div class="mt-4">{{ $departments->links('pagination::bootstrap-5') }}</div>
    </div>
@endsection
