<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\CheckRole;
use App\Console\Commands\CleanupExpiredOtps;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CleanupExpiredOtps::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('otp:cleanup')->hourly();
    })
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->prepend(HandleCors::class);

        // Disable CSRF protection
        $middleware->validateCsrfTokens(except: [
            '*'
        ]);
        
        // Register the role middleware
        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        $middleware->append(StartSession::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();