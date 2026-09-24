@extends('layouts.app')

@section('title', 'Monthly DTR | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header">
            <span class="eyebrow">Employee self-service</span>
            <h1 class="h2 mb-1">Monthly Daily Time Record</h1>
            <p class="text-body-secondary mb-0">Preview or download your attendance for a selected calendar month.</p>
        </header>

        <div class="card filter-panel">
            <div class="card-body p-3 p-md-4">
                <form class="row g-3 align-items-end" method="GET" action="{{ route('employee.dtr.preview') }}">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label" for="month">Month</label>
                        <input class="form-control form-control-lg @error('month') is-invalid @enderror"
                               id="month" name="month" type="month" required value="{{ old('month', $selectedMonth) }}">
                        @error('month')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-auto">
                        <button class="btn btn-workforce btn-lg w-100" type="submit">Preview DTR</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($dtr !== null)
            @include('dtr._preview', [
                'pdfUrl' => route('employee.dtr.pdf', ['month' => $dtr['month']]),
            ])
        @endif
    </div>
@endsection
