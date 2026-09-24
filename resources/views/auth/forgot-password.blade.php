@extends('layouts.app')

@section('title', 'Forgot Password | '.config('app.name'))

@section('content')
    <div class="auth-shell d-flex align-items-center py-5">
        <div class="container">
            <div class="card auth-card mx-auto">
                <div class="card-body p-4 p-md-5">
                    <div class="brand-mark mb-4" aria-hidden="true"><i class="ti ti-key"></i></div>
                    <p class="eyebrow mb-1">Account security</p>
                    <h1 class="h3 fw-bold mb-2">Reset your password</h1>
                    <p class="text-body-secondary mb-4">Enter the email address associated with your account.</p>

                    @if (session('status'))
                        <div class="alert alert-success" role="status" aria-live="polite">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" novalidate>
                        @csrf

                        <div class="mb-4">
                            <label class="form-label" for="email">Email address</label>
                            <input
                                class="form-control form-control-lg @error('email') is-invalid @enderror"
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

                        <button class="btn btn-workforce btn-lg w-100" type="submit">
                            <i class="ti ti-mail-forward me-2" aria-hidden="true"></i>Send reset link
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
