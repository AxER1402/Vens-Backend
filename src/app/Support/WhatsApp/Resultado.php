<?php

namespace App\Support\WhatsApp;

/**
 * Qué pasó al intentar mandar un mensaje.
 */
enum Resultado: string
{
    case Enviado = 'enviado';

    /** El número no tiene cuenta de WhatsApp. No tiene sentido reintentar. */
    case SinWhatsApp = 'sin_whatsapp';

    /** El servicio está caído o sin teléfono vinculado. Se puede reintentar. */
    case SinServicio = 'sin_servicio';

    /** Cualquier otro fallo. Se puede reintentar. */
    case Error = 'error';

    /**
     * Si vale la pena volver a intentarlo en el siguiente minuto.
     */
    public function reintentable(): bool
    {
        return $this === self::SinServicio || $this === self::Error;
    }
}
