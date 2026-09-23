@extends('layouts.app')

@section('title', $employee->user->name.' | '.config('app.name'))

@section('content')
    @php($status = $employee->user->account_status->value)
    <div class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><a class="text-decoration-none" href="{{ route('admin.employees.index') }}">&larr; Employees</a><h1 class="h2 mt-2 mb-1">{{ $employee->user->name }}</h1><p class="text-body-secondary mb-0">{{ $employee->employee_number }}</p></div>
            <a class="btn btn-outline-primary" href="{{ route('admin.employees.edit', $employee) }}">Edit details</a>
        </div>

        @error('invitation')<div class="alert alert-danger">{{ $message }}</div>@enderror
        @error('account_status')<div class="alert alert-danger">{{ $message }}</div>@enderror

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
                    <h2 class="h5 mb-4">Workforce profile</h2>
                    <div class="row g-4">
                        <div class="col-sm-6"><div class="detail-label">Email</div><div>{{ $employee->user->email }}</div></div>
                        <div class="col-sm-6"><div class="detail-label">Department</div><div>{{ $employee->department?->name ?? 'Unassigned' }}</div></div>
                        <div class="col-sm-6"><div class="detail-label">Job title</div><div>{{ $employee->job_title ?? 'Not set' }}</div></div>
                        <div class="col-sm-6"><div class="detail-label">Hired date</div><div>{{ $employee->hired_at?->format('M j, Y') ?? 'Not set' }}</div></div>
                        <div class="col-sm-6"><div class="detail-label">Employment status</div><div>{{ ucfirst($employee->employment_status->value) }}</div></div>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm"><div class="card-body p-4">
                    <h2 class="h5 mb-3">Account access</h2>
                    <p><span class="badge text-bg-{{ $status === 'active' ? 'success' : ($status === 'pending' ? 'warning' : 'secondary') }}">{{ ucfirst($status) }}</span></p>
                    @if ($status === 'pending')
                        <p class="small text-body-secondary">Invitation expires {{ $employee->user->employeeInvitation?->expires_at?->diffForHumans() ?? 'soon' }}.</p>
                        <form method="POST" action="{{ route('admin.employees.invitation.store', $employee) }}">@csrf<button class="btn btn-outline-primary w-100" type="submit">Resend invitation</button></form>
                    @elseif ($status === 'active')
                        <form method="POST" action="{{ route('admin.employees.account.update', $employee) }}">@csrf @method('PATCH')<input name="account_status" type="hidden" value="disabled"><button class="btn btn-outline-danger w-100" type="submit">Disable account</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.employees.account.update', $employee) }}">@csrf @method('PATCH')<input name="account_status" type="hidden" value="active"><button class="btn btn-outline-success w-100" type="submit">Enable account</button></form>
                    @endif
                </div></div>
            </div>
        </div>
    </div>
@endsection
