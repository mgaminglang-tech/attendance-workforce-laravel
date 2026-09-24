<nav class="workforce-nav" aria-label="{{ $navigationLabel ?? 'Primary navigation' }}">
    @can('manage-workforce')
        <p class="workforce-nav-label">Operations</p>
        <a class="workforce-nav-link @if (request()->routeIs('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
            <i class="ti ti-layout-dashboard" aria-hidden="true"></i><span>Dashboard</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('admin.employees.*')) active @endif" href="{{ route('admin.employees.index') }}" @if (request()->routeIs('admin.employees.*')) aria-current="page" @endif>
            <i class="ti ti-users" aria-hidden="true"></i><span>Employees</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('admin.departments.*') && ! request()->routeIs('admin.departments.team-attendance.*')) active @endif" href="{{ route('admin.departments.index') }}" @if (request()->routeIs('admin.departments.*') && ! request()->routeIs('admin.departments.team-attendance.*')) aria-current="page" @endif>
            <i class="ti ti-building-community" aria-hidden="true"></i><span>Departments</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('admin.attendance.*')) active @endif" href="{{ route('admin.attendance.index') }}" @if (request()->routeIs('admin.attendance.*')) aria-current="page" @endif>
            <i class="ti ti-clock-check" aria-hidden="true"></i><span>Attendance</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('admin.team-attendance.*') || request()->routeIs('admin.departments.team-attendance.*')) active @endif" href="{{ route('admin.team-attendance.index') }}" @if (request()->routeIs('admin.team-attendance.*') || request()->routeIs('admin.departments.team-attendance.*')) aria-current="page" @endif>
            <i class="ti ti-activity-heartbeat" aria-hidden="true"></i><span>Team Attendance</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('admin.dtr.*')) active @endif" href="{{ route('admin.dtr.index') }}" @if (request()->routeIs('admin.dtr.*')) aria-current="page" @endif>
            <i class="ti ti-file-description" aria-hidden="true"></i><span>DTR</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('admin.reports.*')) active @endif" href="{{ route('admin.reports.attendance.index') }}" @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif>
            <i class="ti ti-report-analytics" aria-hidden="true"></i><span>Reports</span>
        </a>
    @endcan

    @if (auth()->user()->role === \App\Enums\UserRole::Employee)
        <p class="workforce-nav-label">Personal</p>
        <a class="workforce-nav-link @if (request()->routeIs('employee.dashboard')) active @endif" href="{{ route('employee.dashboard') }}" @if (request()->routeIs('employee.dashboard')) aria-current="page" @endif>
            <i class="ti ti-layout-dashboard" aria-hidden="true"></i><span>Dashboard</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('employee.attendance.index')) active @endif" href="{{ route('employee.attendance.index') }}" @if (request()->routeIs('employee.attendance.index')) aria-current="page" @endif>
            <i class="ti ti-clock-play" aria-hidden="true"></i><span>Attendance</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('employee.attendance.history')) active @endif" href="{{ route('employee.attendance.history') }}" @if (request()->routeIs('employee.attendance.history')) aria-current="page" @endif>
            <i class="ti ti-history" aria-hidden="true"></i><span>History</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('team-attendance.*')) active @endif" href="{{ route('team-attendance.index') }}" @if (request()->routeIs('team-attendance.*')) aria-current="page" @endif>
            <i class="ti ti-users-group" aria-hidden="true"></i><span>My Team</span>
        </a>
        <a class="workforce-nav-link @if (request()->routeIs('employee.dtr.*')) active @endif" href="{{ route('employee.dtr.index') }}" @if (request()->routeIs('employee.dtr.*')) aria-current="page" @endif>
            <i class="ti ti-file-description" aria-hidden="true"></i><span>DTR</span>
        </a>
    @endif

    @can('view-assigned-team-attendance')
        <p class="workforce-nav-label workforce-nav-label-secondary">Assigned department</p>
        <a class="workforce-nav-link @if (request()->routeIs('hr.team-attendance.*')) active @endif" href="{{ route('hr.team-attendance.index') }}" @if (request()->routeIs('hr.team-attendance.*')) aria-current="page" @endif>
            <i class="ti ti-activity-heartbeat" aria-hidden="true"></i><span>Team Attendance</span>
        </a>
    @endcan
    @can('view-assigned-attendance-reports')
        <a class="workforce-nav-link @if (request()->routeIs('hr.reports.*') || request()->routeIs('hr.dtr.*')) active @endif" href="{{ route('hr.reports.attendance.index') }}" @if (request()->routeIs('hr.reports.*') || request()->routeIs('hr.dtr.*')) aria-current="page" @endif>
            <i class="ti ti-report-analytics" aria-hidden="true"></i><span>Reports &amp; DTR</span>
        </a>
    @endcan
</nav>
