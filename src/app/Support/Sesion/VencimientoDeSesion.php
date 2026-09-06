<?php

namespace App\Support\Sesion;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Cuándo vence la sesión de un usuario.
 *
 * La sesión no dura un plazo fijo desde el inicio: dura mientras se use. El
 * reloj se reinicia con cada petición a la API, así que a quien está atendiendo
 * pacientes no se le cierra a media factura, y la computadora que quedó abierta
 * en recepción se cierra sola pasada la hora sin que nadie se acuerde.
 *
 * Medir eso no cuesta nada. Sanctum ya escribe `last_used_at` en cada petición
 * autenticada —lo hace su propio guard, con o sin esta clase—, de modo que la
 * última señal de vida del usuario ya está en la base de datos. Aquí solo se
 * lee. No hace falta ningún proceso vigilando ni consulta adicional alguna.
 *
 * Un token recién creado todavía no tiene `last_used_at`; en ese caso su propia
 * creación es la última actividad.
 */
class VencimientoDeSesion
{
    /**
     * Minutos que puede pasar el token sin usarse. Cero o menos: no vence.
     */
    public static function minutos(): int
    {
        return (int) config('sanctum.inactividad');
    }

    /**
     * Momento en que la sesión se cerrará si no vuelve a haber actividad.
     *
     * Null cuando el vencimiento no aplica: sin plazo configurado, o en las
     * sesiones de primera parte (cookie), que no llevan token personal.
     */
    public static function momento(mixed $token): ?Carbon
    {
        if (self::minutos() <= 0 || ! $token instanceof PersonalAccessToken) {
            return null;
        }

        return self::ultimaActividad($token)->copy()->addMinutes(self::minutos());
    }

    /**
     * ¿Sigue viva la sesión de este token?
     *
     * Se consulta durante la autenticación, antes de que el guard registre la
     * petición en curso, así que lo que se compara es el hueco transcurrido
     * desde la petición anterior. Que es justamente la inactividad.
     */
    public static function siguePorActividad(PersonalAccessToken $token): bool
    {
        $vence = self::momento($token);

        return $vence === null || $vence->isFuture();
    }

    /**
     * El vencimiento tal como se le informa al cliente.
     *
     * @return array{expires_in: int|null, expires_at: string|null}
     */
    public static function paraToken(mixed $token): array
    {
        $vence = self::momento($token);

        if ($vence === null) {
            return ['expires_in' => null, 'expires_at' => null];
        }

        return [
            // Se trunca hacia abajo para no prometer más tiempo del que queda.
            'expires_in' => max(0, (int) now()->diffInSeconds($vence, false)),
            'expires_at' => $vence->toIso8601String(),
        ];
    }

    /**
     * La última señal de vida del token.
     */
    private static function ultimaActividad(PersonalAccessToken $token): Carbon
    {
        return $token->last_used_at ?? $token->created_at;
    }
}
