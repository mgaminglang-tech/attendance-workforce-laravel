<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    @auth
        <nav class="navbar navbar-expand-xl navbar-dark app-navbar" aria-label="Primary navigation">
            <div class="container-xxl">
                <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route(auth()->user()->role->dashboardRouteName()) }}">
                    <span class="navbar-brand-mark" aria-hidden="true">WM</span>
                    <span>
                        <span class="navbar-brand-title">Workforce Management</span>
                        <span class="navbar-brand-subtitle">Attendance &amp; records</span>
                    </span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-navigation"
                        aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="main-navigation">
                    <div class="navbar-nav ms-xl-4 me-xl-auto py-3 py-xl-0">
                    @can('manage-workforce')
                        <a class="nav-link @if (request()->routeIs('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
                        <a class="nav-link @if (request()->routeIs('admin.employees.*')) active @endif" href="{{ route('admin.employees.index') }}" @if (request()->routeIs('admin.employees.*')) aria-current="page" @endif>Employees</a>
                        <a class="nav-link @if (request()->routeIs('admin.departments.*') && ! request()->routeIs('admin.departments.team-attendance.*')) active @endif" href="{{ route('admin.departments.index') }}" @if (request()->routeIs('admin.departments.*') && ! request()->routeIs('admin.departments.team-attendance.*')) aria-current="page" @endif>Departments</a>
                        <a class="nav-link @if (request()->routeIs('admin.attendance.*')) active @endif" href="{{ route('admin.attendance.index') }}" @if (request()->routeIs('admin.attendance.*')) aria-current="page" @endif>Attendance</a>
                        <a class="nav-link @if (request()->routeIs('admin.team-attendance.*') || request()->routeIs('admin.departments.team-attendance.*')) active @endif" href="{{ route('admin.team-attendance.index') }}" @if (request()->routeIs('admin.team-attendance.*') || request()->routeIs('admin.departments.team-attendance.*')) aria-current="page" @endif>Team Attendance</a>
                        <a class="nav-link @if (request()->routeIs('admin.dtr.*')) active @endif" href="{{ route('admin.dtr.index') }}" @if (request()->routeIs('admin.dtr.*')) aria-current="page" @endif>DTR</a>
                        <a class="nav-link @if (request()->routeIs('admin.reports.*')) active @endif" href="{{ route('admin.reports.attendance.index') }}" @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif>Reports</a>
                    @endcan
                    @if (auth()->user()->role === \App\Enums\UserRole::Employee)
                        <a class="nav-link @if (request()->routeIs('employee.dashboard')) active @endif" href="{{ route('employee.dashboard') }}" @if (request()->routeIs('employee.dashboard')) aria-current="page" @endif>Dashboard</a>
                        <a class="nav-link @if (request()->routeIs('employee.attendance.index')) active @endif" href="{{ route('employee.attendance.index') }}" @if (request()->routeIs('employee.attendance.index')) aria-current="page" @endif>Attendance</a>
                        <a class="nav-link @if (request()->routeIs('employee.attendance.history')) active @endif" href="{{ route('employee.attendance.history') }}" @if (request()->routeIs('employee.attendance.history')) aria-current="page" @endif>History</a>
                        <a class="nav-link @if (request()->routeIs('team-attendance.*')) active @endif" href="{{ route('team-attendance.index') }}" @if (request()->routeIs('team-attendance.*')) aria-current="page" @endif>Team Attendance</a>
                        <a class="nav-link @if (request()->routeIs('employee.dtr.*')) active @endif" href="{{ route('employee.dtr.index') }}" @if (request()->routeIs('employee.dtr.*')) aria-current="page" @endif>DTR</a>
                    @endif
                    @can('view-assigned-team-attendance')
                        <a class="nav-link @if (request()->routeIs('hr.team-attendance.*')) active @endif" href="{{ route('hr.team-attendance.index') }}" @if (request()->routeIs('hr.team-attendance.*')) aria-current="page" @endif>Department Team</a>
                    @endcan
                    @can('view-assigned-attendance-reports')
                        <a class="nav-link @if (request()->routeIs('hr.reports.*') || request()->routeIs('hr.dtr.*')) active @endif" href="{{ route('hr.reports.attendance.index') }}" @if (request()->routeIs('hr.reports.*') || request()->routeIs('hr.dtr.*')) aria-current="page" @endif>HR Reports</a>
                    @endcan
                    </div>
                    <div class="navbar-account d-flex flex-column flex-xl-row align-items-xl-center gap-3 pb-3 pb-xl-0">
                        <div class="text-xl-end">
                            <div class="navbar-user-name">{{ auth()->user()->name }}</div>
                            <div class="navbar-user-role">
                                @can('view-assigned-team-attendance')
                                    HR Representative
                                @else
                                    {{ auth()->user()->role === \App\Enums\UserRole::Admin ? 'Global Admin' : 'Employee' }}
                                @endcan
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                        @csrf
                            <button class="btn btn-sm btn-outline-light w-100" type="submit">Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    @endauth

    <main id="main-content" tabindex="-1">
        @if (session('status'))
            <div class="container-xxl pt-4">
                <div class="alert alert-success alert-dismissible fade show mb-0" role="status">
                    {{ session('status') }}
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                </div>
            </div>
        @endif
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
