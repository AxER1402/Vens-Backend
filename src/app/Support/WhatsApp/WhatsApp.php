<?php

namespace App\Support\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * El cliente del servicio de WhatsApp Web (docker/whatsapp).
 *
 * Laravel no habla con WhatsApp directamente: se lo pide por HTTP a ese
 * contenedor, que tiene abierto un WhatsApp Web vinculado al teléfono de la
 * clínica. Esta clase es el único sitio que sabe cómo.
 */
class WhatsApp
{
    /** El servicio no responde: el contenedor está apagado o arrancando. */
    public const SIN_SERVICIO = 'sin_servicio';

    /**
     * Cómo está la conexión.
     *
     * Nunca lanza: la pantalla de configuración tiene que poder pintarse aunque
     * el contenedor esté caído, y decir justo eso.
     *
     * @return array{estado: string, qr: ?string, numero: ?string}
     */
    public static function estado(): array
    {
        try {
            $respuesta = self::http()->get('/estado');

            if ($respuesta->successful()) {
                return [
                    'estado' => (string) $respuesta->json('estado', self::SIN_SERVICIO),
                    'qr' => $respuesta->json('qr'),
                    'numero' => $respuesta->json('numero'),
                ];
            }
        } catch (ConnectionException) {
            // Cae al estado de abajo.
        }

        return ['estado' => self::SIN_SERVICIO, 'qr' => null, 'numero' => null];
    }

    /**
     * Mandar un mensaje.
     *
     * Devuelve qué pasó en vez de true/false porque no todos los fallos son
     * iguales: un número sin WhatsApp no va a tenerlo dentro de un minuto, pero
     * un servicio desconectado sí puede volver.
     */
    public static function enviar(string $telefono, string $mensaje): Resultado
    {
        try {
            $respuesta = self::http()->timeout(30)->post('/enviar', [
                'telefono' => self::numeroInternacional($telefono),
                'mensaje' => $mensaje,
            ]);
        } catch (ConnectionException) {
            return Resultado::SinServicio;
        }

        return match (true) {
            $respuesta->successful() => Resultado::Enviado,
            $respuesta->status() === 404 => Resultado::SinWhatsApp,
            $respuesta->status() === 503 => Resultado::SinServicio,
            default => Resultado::Error,
        };
    }

    /**
     * Desvincular el teléfono. El servicio vuelve a pedir QR.
     */
    public static function cerrarSesion(): void
    {
        try {
            self::http()->post('/cerrar-sesion');
        } catch (ConnectionException) {
            // Si no responde, no hay sesión que cerrar.
        }
    }

    /**
     * El número con su código de país.
     *
     * En el sistema los teléfonos se guardan como se dictan en el mostrador:
     * ocho dígitos, sin el 502. WhatsApp necesita el número completo, así que
     * a los de largo local se les antepone el código; los que ya son más
     * largos se entienden como internacionales y se dejan como vienen.
     */
    public static function numeroInternacional(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', $telefono);

        if (strlen($digitos) === 8) {
            return config('services.whatsapp.codigo_pais').$digitos;
        }

        return $digitos;
    }

    private static function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.whatsapp.url'))
            ->withHeaders(['X-Token' => (string) config('services.whatsapp.token')])
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(5);
    }
}
