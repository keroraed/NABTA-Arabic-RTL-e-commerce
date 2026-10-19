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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // There is no standalone login route: staff authenticate through the
        // Filament panel, and the POS and report routes share that session.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        // Staff language choice. The storefront is unaffected: customers
        // always see Arabic.
        $middleware->web(append: [
            App\Http\Middleware\SetLocale::class,
        ]);

        // Paymob's server-to-server callback cannot carry a CSRF token; it is
        // authenticated by its HMAC signature instead (PaymobService).
        $middleware->preventRequestForgery(except: ['paymob/webhook']);

        $middleware->alias([
            'permission' => App\Http\Middleware\EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
