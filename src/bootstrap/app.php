<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        // A un invitado no se le redirige a ninguna parte. Esto es una API sin
        // pantallas: no existe una ruta 'login' a la que mandarlo, y el intento
        // de resolverla convertía cualquier petición sin token —o con uno ya
        // revocado— en un error 500 en lugar del 401 que corresponde.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // El 401 es la señal con la que el frontend cierra la sesión y avisa de
        // que venció, así que se responde con el mismo formato que el resto de
        // la API y con un mensaje que se pueda mostrar tal cual.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Su sesión no es válida o ha expirado. Vuelva a iniciar sesión.',
            ], 401);
        });
    })->create();
