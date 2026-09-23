<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
    @auth
        <nav class="navbar navbar-dark app-navbar shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="{{ route(auth()->user()->role->dashboardRouteName()) }}">
                    Workforce Management
                </a>
                <div class="d-flex align-items-center gap-3">
                    @can('manage-workforce')
                        <a class="link-light text-decoration-none" href="{{ route('admin.employees.index') }}">Employees</a>
                        <a class="link-light text-decoration-none" href="{{ route('admin.departments.index') }}">Departments</a>
                        <a class="link-light text-decoration-none" href="{{ route('admin.attendance.index') }}">Attendance</a>
                        <a class="link-light text-decoration-none" href="{{ route('admin.team-attendance.index') }}">Team Attendance</a>
                    @endcan
                    @if (auth()->user()->role === \App\Enums\UserRole::Employee)
                        <a class="link-light text-decoration-none" href="{{ route('employee.attendance.index') }}">Attendance</a>
                        <a class="link-light text-decoration-none" href="{{ route('employee.attendance.history') }}">History</a>
                    @endif
                    @can('view-own-team-attendance')
                        <a class="link-light text-decoration-none" href="{{ route('team-attendance.index') }}">Team Attendance</a>
                    @endcan
                    @can('view-assigned-team-attendance')
                        <a class="link-light text-decoration-none" href="{{ route('hr.team-attendance.index') }}">HR Workspace</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-light" type="submit">Sign out</button>
                    </form>
                </div>
            </div>
        </nav>
    @endauth

    <main>
        @if (session('status'))
            <div class="container pt-4">
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            </div>
        @endif
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
