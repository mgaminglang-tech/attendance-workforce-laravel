<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@auth workforce-body @else guest-body @endauth">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    @auth
        <div class="workforce-layout">
            <aside class="workforce-sidebar d-none d-lg-flex" aria-label="Desktop application navigation">
                <a class="workforce-brand" href="{{ route(auth()->user()->role->dashboardRouteName()) }}">
                    <span class="workforce-brand-mark" aria-hidden="true"><i class="ti ti-clock-shield"></i></span>
                    <span>
                        <span class="workforce-brand-title">Workforce</span>
                        <span class="workforce-brand-subtitle">Operations system</span>
                    </span>
                </a>

                @include('layouts._navigation', ['navigationLabel' => 'Desktop primary navigation'])

                <div class="workforce-account">
                    <span class="avatar avatar-sm workforce-account-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::of(auth()->user()->name)->squish()->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->implode('') }}</span>
                    <span class="workforce-account-copy">
                        <strong>{{ auth()->user()->name }}</strong>
                        <small>
                            @can('view-assigned-team-attendance')
                                HR Representative
                            @else
                                {{ auth()->user()->role === \App\Enums\UserRole::Admin ? 'Global Admin' : 'Employee' }}
                            @endcan
                        </small>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="workforce-signout" type="submit" aria-label="Sign out" title="Sign out">
                            <i class="ti ti-logout" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </aside>

            <div class="workforce-stage">
                <header class="workforce-mobile-bar d-lg-none">
                    <a class="workforce-mobile-brand" href="{{ route(auth()->user()->role->dashboardRouteName()) }}">
                        <span class="workforce-brand-mark" aria-hidden="true"><i class="ti ti-clock-shield"></i></span>
                        <span>Workforce</span>
                    </a>
                    <button class="navbar-toggler workforce-menu-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobile-navigation"
                            aria-controls="mobile-navigation" aria-label="Open navigation">
                        <i class="ti ti-menu-2" aria-hidden="true"></i>
                    </button>
                </header>

                <div class="offcanvas offcanvas-start workforce-mobile-navigation" id="mobile-navigation" tabindex="-1" aria-labelledby="mobile-navigation-title">
                    <div class="offcanvas-header">
                        <div>
                            <p class="workforce-brand-title mb-0" id="mobile-navigation-title">Workforce</p>
                            <p class="workforce-brand-subtitle mb-0">Operations system</p>
                        </div>
                        <button class="btn-close btn-close-white" type="button" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
                    </div>
                    <div class="offcanvas-body">
                        @include('layouts._navigation', ['navigationLabel' => 'Mobile primary navigation'])
                    </div>
                    <div class="workforce-mobile-account">
                        <div>
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>
                                @can('view-assigned-team-attendance') HR Representative
                                @else {{ auth()->user()->role === \App\Enums\UserRole::Admin ? 'Global Admin' : 'Employee' }}
                                @endcan
                            </small>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-outline-light" type="submit"><i class="ti ti-logout me-2" aria-hidden="true"></i>Sign out</button>
                        </form>
                    </div>
                </div>

                <main id="main-content" tabindex="-1">
                    @if (session('status'))
                        <div class="container-xxl pt-3">
                            <div class="alert alert-success alert-dismissible fade show mb-0" role="status">
                                <i class="ti ti-circle-check me-2" aria-hidden="true"></i>{{ session('status') }}
                                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                            </div>
                        </div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <main id="main-content" tabindex="-1">
            @yield('content')
        </main>
    @endauth

    @stack('scripts')
</body>
</html>
