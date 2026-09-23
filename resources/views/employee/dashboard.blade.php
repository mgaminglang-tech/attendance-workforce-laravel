@extends('layouts.app')

@section('title', 'Employee Dashboard | '.config('app.name'))

@section('content')
    <div class="container py-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <span class="badge text-bg-secondary mb-3">Employee</span>
                <h1 class="display-6 fw-bold">Employee Dashboard</h1>
                <p class="lead text-body-secondary">Record your Time In and Time Out using secure server-authoritative timekeeping.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-workforce" href="{{ route('employee.attendance.index') }}">Open timekeeping</a>
                    <a class="btn btn-outline-secondary" href="{{ route('employee.attendance.history') }}">Attendance history</a>
                </div>
            </div>
        </div>
    </div>
@endsection
