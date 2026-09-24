@extends('layouts.app')

@section('title', 'Reset Password | '.config('app.name'))

@section('content')
    <div class="auth-shell d-flex align-items-center py-5">
        <div class="container">
            <div class="card auth-card mx-auto">
                <div class="card-body p-4 p-md-5">
                    <div class="brand-mark mb-4" aria-hidden="true"><i class="ti ti-lock-check"></i></div>
                    <p class="eyebrow mb-1">Account security</p>
                    <h1 class="h3 fw-bold mb-2">Reset your password</h1>
                    <p class="text-body-secondary mb-4">Choose a new password for your workforce account.</p>

                    <form method="POST" action="{{ route('password.update') }}" novalidate>
                        @csrf
                        <input name="token" type="hidden" value="{{ $token }}">

                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input
                                class="form-control @error('email') is-invalid @enderror"
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email', $email) }}"
                                autocomplete="email"
                                required
                                autofocus
                            >
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">New password</label>
                            <input
                                class="form-control @error('password') is-invalid @enderror"
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                required
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Use at least 12 characters with upper and lowercase letters, a number, and a symbol.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirm new password</label>
                            <input
                                class="form-control"
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <button class="btn btn-workforce btn-lg w-100" type="submit">
                            <i class="ti ti-lock-check me-2" aria-hidden="true"></i>Reset password
                        </button>
                    </form>

                    <a class="auth-back-link" href="{{ route('login') }}">
                        <i class="ti ti-arrow-left" aria-hidden="true"></i>Back to sign in
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
