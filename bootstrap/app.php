<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(at: function (): array {
            $url = config('app.url');
            $parts = is_string($url) ? parse_url($url) : false;
            $host = $parts['host'] ?? null;

            abort_if(
                ! is_string($url)
                || filter_var($url, FILTER_VALIDATE_URL) === false
                || ($parts['scheme'] ?? null) !== 'https'
                || ! is_string($host)
                || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
                || filter_var($host, FILTER_VALIDATE_IP) !== false
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['query'])
                || isset($parts['fragment']),
                503,
                'APP_URL must be a valid canonical HTTPS URL.',
            );

            return ['\A'.preg_quote(strtolower($host)).'\z'];
        }, subdomains: false);

        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            'role' => RequireRole::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request): string => route('login'));
        $middleware->redirectUsersTo(function (Request $request): string {
            return route($request->user()->role->dashboardRouteName());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
