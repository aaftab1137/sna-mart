<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',  
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'AdminLogin' => \App\Http\Middleware\AdminLoginCheck::class,
            'Login'    => \App\Http\Middleware\LoginCheck::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Throwable $e, $request) {
            return response()->json([
                'error' => 'Something went wrong',
                'message' => $e->getMessage(),
            ], 500);
        });
    })->create();
