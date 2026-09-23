@extends('layouts.app')

@section('title', 'Set Up Account | '.config('app.name'))

@section('content')
    <div class="auth-shell d-flex align-items-center py-5">
        <div class="container">
            <div class="card auth-card mx-auto"><div class="card-body p-4 p-md-5">
                <div class="brand-mark mb-4" aria-hidden="true">WM</div>
                <h1 class="h3 fw-bold mb-2">Set up your account</h1>
                <p class="text-body-secondary mb-4">Welcome, {{ $user->name }}. Choose a strong password to activate your workforce account.</p>
                <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}">
                    @csrf
                    <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Use at least 12 characters with upper and lowercase letters, a number, and a symbol.</div></div>
                    <div class="mb-4"><label class="form-label" for="password_confirmation">Confirm password</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                    <button class="btn btn-workforce w-100" type="submit">Activate account</button>
                </form>
            </div></div>
        </div>
    </div>
@endsection
