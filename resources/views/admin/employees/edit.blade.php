@extends('layouts.app')

@section('title', 'Edit Employee | '.config('app.name'))

@section('content')
    <div class="container py-5">
        <h1 class="h2 mb-4">Edit Employee</h1>
        <div class="card border-0 shadow-sm">
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
