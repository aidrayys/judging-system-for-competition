<?php

use App\Http\Middleware\EnsureJudgingOpen;
use App\Http\Middleware\EnsureUserHasRole;
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
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'judging.open' => EnsureJudgingOpen::class,
        ]);

        // Global Back/Forward fix: dynamic pages are always re-fetched.
        $middleware->appendToGroup('web', \App\Http\Middleware\PreventStaleHtmlCache::class);

        // Trust the local reverse proxy (e.g. ngrok) so that X-Forwarded-Proto
        // and other forwarded headers are honoured. This makes Laravel generate
        // HTTPS URLs when the public ngrok endpoint is accessed over HTTPS.
        // ::1 is included because a real web server (Apache) also listens on
        // IPv6, and ngrok's "localhost" upstream may then connect from ::1.
        $middleware->trustProxies(
            ['127.0.0.1', '::1'],
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
