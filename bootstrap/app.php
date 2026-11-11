<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware([])->group(base_path('routes/agentapi.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'legacy.session' => \App\Http\Middleware\LegacySession::class,
            'legacy.network.notblocked' => \App\Http\Middleware\EnsureNetworkNotBlocked::class,
            'legacy.admin' => \App\Http\Middleware\AdminOnly::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // CSRF is ON for everything. Exempt later (explicitly): AgentApi/*, UPIGateway callback, SmsSendCron.
        // $middleware->validateCsrfTokens(except: ['AgentApi/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
