@extends('layouts.app')

@section('title', 'Add Employee | '.config('app.name'))

@section('content')
    <div class="container py-5">
        <div class="mb-4">
            <h1 class="h2 mb-1">Add Employee</h1>
            <p class="text-body-secondary mb-0">The employee will receive a secure link to set their own password.</p>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.employees.store') }}">
                    @csrf
                    @include('admin.employees._form')
                </form>
            </div>
        </div>
    </div>
@endsection
