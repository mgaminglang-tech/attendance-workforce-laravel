@extends('layouts.app')

@section('title', 'Sign in | '.config('app.name'))

@section('content')
    <div class="auth-shell d-flex align-items-center py-5">
        <div class="container">
            <div class="card auth-card mx-auto">
                <div class="card-body p-4 p-md-5">
                    <div class="brand-mark mb-4" aria-hidden="true">WM</div>
                    <p class="eyebrow mb-1">Workforce Management</p>
                    <h1 class="h3 fw-bold mb-2">Welcome back</h1>
                    <p class="text-body-secondary mb-4">Sign in to the Employee Timekeeping &amp; Workforce Management System.</p>

                    <form method="POST" action="{{ route('login.store') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input
                                class="form-control @error('email') is-invalid @enderror"
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                required
                                autofocus
                            >
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input
                                class="form-control @error('password') is-invalid @enderror"
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <button class="btn btn-workforce btn-lg w-100" type="submit">Sign in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
