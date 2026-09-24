@extends('layouts.app')

@section('title', 'Add Employee | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header">
            <h1 class="h2 mb-1">Add Employee</h1>
            <p class="text-body-secondary mb-0">The employee will receive a secure link to set their own password.</p>
        </header>
        <div class="card surface-card">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.employees.store') }}">
                    @csrf
                    @include('admin.employees._form')
                </form>
            </div>
        </div>
    </div>
@endsection
