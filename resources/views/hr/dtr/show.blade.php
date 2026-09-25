@extends('layouts.app')

@section('title', 'Department Employee DTR | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header dtr-selection-header">
            <span class="eyebrow">HR Representative access</span>
            <h1 class="h2 mb-1">{{ $employee->user->name }} Monthly DTR</h1>
            <p class="text-body-secondary mb-0">{{ $employee->employee_number }} · {{ $employee->department->name }}</p>
        </header>

        <div class="card filter-panel dtr-selection-panel">
            <div class="card-body p-3 p-md-4">
                <form class="row g-3 align-items-end dtr-control-row" method="GET" action="{{ route('hr.dtr.preview', $employee) }}">
                    <div class="col-12 col-lg-7">
                        @include('dtr._employee-picker', [
                            'id' => 'hr-employee',
                            'employees' => $employees,
                            'selectedEmployee' => $employee,
                            'updatesFormAction' => true,
                        ])
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        @include('dtr._month-picker', ['id' => 'month', 'value' => old('month', $selectedMonth)])
                    </div>
                    <div class="col-12 col-md-auto dtr-submit-column">
                        <button class="btn btn-workforce btn-lg w-100 text-nowrap" type="submit">Preview DTR</button>
                    </div>
                    <div class="col-12">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.reports.attendance.index') }}">
                            <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Back to Reports
                        </a>
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
