@extends('layouts.app')

@section('title', 'Add Department | '.config('app.name'))

@section('content')
    <div class="container-xxl page-shell">
        <header class="page-header"><h1 class="h2 mb-1">Add Department</h1><p class="text-body-secondary mb-0">Create an assignment option for workforce profiles.</p></header>
        <div class="card surface-card"><div class="card-body p-4"><form method="POST" action="{{ route('admin.departments.store') }}">@csrf @include('admin.departments._form')</form></div></div>
    </div>
@endsection
