@extends('layouts.app')

@section('title', 'Edit Employee | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header">
            <h1 class="h2 mb-1">Edit Employee</h1>
            <p class="text-body-secondary mb-0">Update workforce profile and department details.</p>
        </header>
        <div class="card surface-card">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.employees.update', $employee) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.employees._form')
                </form>
            </div>
        </div>
    </div>
@endsection
