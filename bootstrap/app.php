<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\PreventDuplicateRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Mendaftarkan middleware global
        // $middleware->append([...]);
        
        // Mendaftarkan middleware grup
        $middleware->group('api', [
            // Middleware untuk grup API
        ]);
        
        // Mendaftarkan middleware dengan alias
        $middleware->alias([
            'prevent-duplicate' => PreventDuplicateRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
