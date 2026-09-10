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
        // En producción la aplicación no recibe las peticiones directamente:
        // delante hay un proxy que resuelve el HTTPS y las reenvía. Sin esto,
        // Laravel tomaría la IP del proxy como la del cliente, y el
        // throttle:5,1 de la recuperación de contraseña pasaría a ser un cupo
        // único para toda la clínica en vez de uno por persona.
        //
        // Se confía en cualquier proxy ('*') y no en una IP concreta porque la
        // de la red de Docker cambia entre reconstrucciones. Es seguro aquí: el
        // contenedor solo publica su puerto en 127.0.0.1, así que lo único que
        // puede conectarse —y por tanto lo único que puede escribir esas
        // cabeceras— es el proxy de la propia máquina.
        $middleware->trustProxies(at: '*');

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
