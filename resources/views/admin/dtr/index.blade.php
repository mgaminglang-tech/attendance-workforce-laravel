@extends('layouts.app')

@section('title', 'Employee DTR | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header dtr-selection-header">
            <span class="eyebrow">Global administration</span>
            <h1 class="h2 mb-1">Employee Monthly DTR</h1>
            <p class="text-body-secondary mb-0">Preview or download an employee's canonical attendance record.</p>
        </header>

        <div class="card filter-panel dtr-selection-panel">
            <div class="card-body p-3 p-md-4">
                <form class="row g-3 align-items-end dtr-control-row" method="GET" action="{{ route('admin.dtr.preview') }}">
                    <div class="col-12 col-lg-7">
                        @include('dtr._employee-picker', [
                            'id' => 'employee_id',
                            'name' => 'employee_id',
                            'employees' => $employees,
                            'selectedEmployee' => $selectedEmployee,
                        ])
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        @include('dtr._month-picker', ['id' => 'month', 'value' => old('month', $selectedMonth)])
                    </div>
                    <div class="col-12 col-md-auto dtr-submit-column">
                        <button class="btn btn-workforce btn-lg w-100 text-nowrap" type="submit">Preview DTR</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($dtr !== null)
            @include('dtr._preview', [
                'pdfUrl' => route('admin.dtr.pdf', [
                    'employee_id' => $selectedEmployee->id,
                    'month' => $dtr['month'],
                ]),
            ])
        @endif
    </div>
@endsection
