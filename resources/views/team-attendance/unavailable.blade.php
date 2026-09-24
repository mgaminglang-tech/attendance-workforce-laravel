@extends('layouts.app')

@section('title', 'Team Attendance Unavailable | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <div class="card surface-card content-narrow mx-auto">
            <div class="card-body p-5 text-center">
                <h1 class="h3">Team Attendance unavailable</h1>
                <p class="text-body-secondary mb-0">{{ $message }}</p>
            </div>
        </div>
    </div>
@endsection
