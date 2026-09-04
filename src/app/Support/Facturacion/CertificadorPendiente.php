<?php

namespace App\Support\Facturacion;

use App\Models\Invoice;

/**
 * El certificador que todavía no existe.
 *
 * Deja el documento marcado como pendiente y dice por qué. No inventa un
 * número de autorización: una factura con un UUID falso se ve certificada y no
 * lo está, que es la peor de las dos formas de fallar.
 */
class CertificadorPendiente implements Certificador
{
    public function certificar(Invoice $documento): array
    {
        return [
            'estado' => 'Pendiente',
            'uuid' => null,
            'serie' => null,
            'numero' => null,
            'certificador' => null,
            'mensaje' => 'Documento emitido sin certificar: todavía no hay conexión con el certificador de la SAT. '
                . 'Queda registrado y puede certificarse después.',
        ];
    }
}
