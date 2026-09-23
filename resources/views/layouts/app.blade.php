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
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-light" type="submit">Sign out</button>
                </form>
            </div>
        </nav>
    @endauth

    <main>
        @yield('content')
    </main>
</body>
</html>
