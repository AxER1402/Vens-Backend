<?php

namespace App\Http\Middleware;

use App\Support\Sesion\VencimientoDeSesion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le dice al cliente, en cada respuesta, cuándo se cerrará la sesión.
 *
 * Hace falta porque el plazo ya no es fijo. Si el frontend se quedara con el
 * `expires_at` que recibió al iniciar sesión, cerraría la pantalla a la hora
 * exacta aunque el usuario llevara todo ese rato trabajando: justo lo que se
 * quiso evitar. Con estas cabeceras solo tiene que reiniciar su cuenta atrás
 * con cada respuesta que reciba.
 *
 * No cuesta ninguna consulta: el token ya viene cargado en memoria por el guard
 * que acaba de autenticar la petición.
 */
class AnunciaVencimientoDeSesion
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $vencimiento = VencimientoDeSesion::paraToken(
            $request->user()?->currentAccessToken()
        );

        // Sin vencimiento no se anuncia nada, para que la ausencia de la
        // cabecera signifique «esta sesión no caduca» y no «se me olvidó».
        if ($vencimiento['expires_at'] !== null) {
            $response->headers->set('X-Session-Expires-In', (string) $vencimiento['expires_in']);
            $response->headers->set('X-Session-Expires-At', $vencimiento['expires_at']);
        }

        return $response;
    }
}
