@extends('layouts.app')

@section('title', 'Department Workspaces | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header">
            <span class="eyebrow">Global Admin</span>
            <h1 class="h2 mb-1">Department Team Attendance</h1>
            <p class="text-body-secondary mb-0">Open any department's read-only operational attendance workspace.</p>
        </header>
        <div class="card surface-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Department</th><th>Employees</th><th>HR Representative</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse ($departments as $department)
                            <tr>
                                <td><span class="fw-semibold">{{ $department->name }}</span><br><span class="small text-body-secondary">{{ $department->code }}</span></td>
                                <td>{{ $department->employees_count }}</td>
                                <td>{{ $department->hrAssignment?->user?->name ?? 'Not assigned' }}</td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.departments.team-attendance.show', $department) }}">Open workspace</a></td>
                            </tr>
                        @empty
                            <tr><td class="empty-state" colspan="4">No departments have been created.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $departments->links('pagination::bootstrap-5') }}</div>
    </div>
@endsection
