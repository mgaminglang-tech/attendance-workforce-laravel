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
        <nav class="navbar navbar-expand-lg navbar-dark app-navbar shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="{{ route(auth()->user()->role->dashboardRouteName()) }}">
                    Workforce Management
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-navigation"
                        aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="main-navigation">
                    <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2 py-3 py-lg-0">
                    @can('manage-workforce')
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">Dashboard</a>
                        <a class="nav-link" href="{{ route('admin.employees.index') }}">Employees</a>
                        <a class="nav-link" href="{{ route('admin.departments.index') }}">Departments</a>
                        <a class="nav-link" href="{{ route('admin.attendance.index') }}">Attendance</a>
                        <a class="nav-link" href="{{ route('admin.team-attendance.index') }}">Team Attendance</a>
                        <a class="nav-link" href="{{ route('admin.dtr.index') }}">DTR</a>
                        <a class="nav-link" href="{{ route('admin.reports.attendance.index') }}">Reports</a>
                    @endcan
                    @if (auth()->user()->role === \App\Enums\UserRole::Employee)
                        <a class="nav-link" href="{{ route('employee.attendance.index') }}">Attendance</a>
                        <a class="nav-link" href="{{ route('employee.attendance.history') }}">History</a>
                        <a class="nav-link" href="{{ route('employee.dtr.index') }}">DTR</a>
                    @endif
                    @can('view-own-team-attendance')
                        <a class="nav-link" href="{{ route('team-attendance.index') }}">Team Attendance</a>
                    @endcan
                    @can('view-assigned-team-attendance')
                        <a class="nav-link" href="{{ route('hr.team-attendance.index') }}">HR Workspace</a>
                    @endcan
                    @can('view-assigned-attendance-reports')
                        <a class="nav-link" href="{{ route('hr.reports.attendance.index') }}">Reports</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-light" type="submit">Sign out</button>
                    </form>
                    </div>
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
