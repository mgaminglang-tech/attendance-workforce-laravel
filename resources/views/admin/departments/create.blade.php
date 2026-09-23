@extends('layouts.app')

@section('title', 'Add Department | '.config('app.name'))

@section('content')
    <div class="container py-5"><h1 class="h2 mb-4">Add Department</h1><div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="POST" action="{{ route('admin.departments.store') }}">@csrf @include('admin.departments._form')</form></div></div></div>
@endsection
