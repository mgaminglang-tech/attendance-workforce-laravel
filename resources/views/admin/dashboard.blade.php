@extends('layouts.app')

@section('title', 'Global Admin Dashboard | '.config('app.name'))

@section('content')
    <div class="container py-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <span class="badge text-bg-primary mb-3">Global Admin</span>
                <h1 class="display-6 fw-bold">Global Admin Dashboard</h1>
                <p class="lead text-body-secondary mb-0">Manage workforce records and department attendance workspaces.</p>
            </div>
        </div>
    </div>
@endsection
