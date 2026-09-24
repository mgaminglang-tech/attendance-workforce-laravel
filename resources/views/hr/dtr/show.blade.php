@extends('layouts.app')

@section('title', 'Department Employee DTR | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header">
            <span class="eyebrow">HR Representative access</span>
            <h1 class="h2 mb-1">{{ $employee->user->name }} Monthly DTR</h1>
            <p class="text-body-secondary mb-0">{{ $employee->employee_number }} · {{ $employee->department->name }}</p>
        </header>

        <div class="card filter-panel">
            <div class="card-body p-3 p-md-4">
                <form class="row g-3 align-items-end" method="GET" action="{{ route('hr.dtr.preview', $employee) }}">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label" for="month">Month</label>
                        <input class="form-control form-control-lg @error('month') is-invalid @enderror"
                               id="month" name="month" type="month" required value="{{ old('month', $selectedMonth) }}">
                        @error('month')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-auto">
                        <button class="btn btn-workforce btn-lg w-100" type="submit">Preview DTR</button>
                    </div>
                    <div class="col-12 col-md-auto">
                        <a class="btn btn-outline-secondary btn-lg w-100" href="{{ route('hr.reports.attendance.index') }}">Back to Reports</a>
                    </div>
                </form>
            </div>
        </div>

        @include('dtr._preview', [
            'pdfUrl' => route('hr.dtr.pdf', [
                'employee' => $employee,
                'month' => $dtr['month'],
            ]),
        ])
    </div>
@endsection
