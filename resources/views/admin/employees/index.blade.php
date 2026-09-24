@extends('layouts.app')

@section('title', 'Employees | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
            <div>
                <h1 class="h2 mb-1">Employees</h1>
                <p class="text-body-secondary mb-0">Manage workforce profiles and account access.</p>
            </div>
            <a class="btn btn-workforce" href="{{ route('admin.employees.create') }}">Add Employee</a>
        </header>

        <form class="card filter-panel mb-4" method="GET">
            <div class="card-body row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label" for="search">Search</label>
                    <input class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Name, email, number, or job title">
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="department">Department</label>
                    <select class="form-select" id="department" name="department">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="account_status">Account</label>
                    <select class="form-select" id="account_status" name="account_status">
                        <option value="">All statuses</option>
                        @foreach ($accountStatuses as $status)
                            <option value="{{ $status->value }}" @selected(request('account_status') === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button class="btn btn-workforce flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.employees.index') }}">Reset</a>
                </div>
            </div>
        </form>

        <div class="card surface-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Employee</th><th>Number</th><th>Department</th><th>Account</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php($status = $employee->user->account_status->value)
                            <tr>
                                <td><div class="fw-semibold">{{ $employee->user->name }}</div><div class="small text-body-secondary">{{ $employee->user->email }}</div></td>
                                <td>{{ $employee->employee_number }}</td>
                                <td>{{ $employee->department?->name ?? 'Unassigned' }}</td>
                                <td><span class="badge status-badge status-badge-{{ $status }}">{{ ucfirst($status) }}</span></td>
                                <td class="text-end table-actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.employees.show', $employee) }}">View</a> <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.employees.edit', $employee) }}">Edit</a></td>
                            </tr>
                        @empty
                            <tr><td class="empty-state" colspan="5">No employees match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $employees->links('pagination::bootstrap-5') }}</div>
    </div>
@endsection
