<?php

namespace App\Support\Facturacion;

use App\Models\Invoice;
use Illuminate\Support\Str;

/**
 * El certificador del propio sistema.
 *
 * Arma la autorización con la estructura de un DTE: un UUID en mayúsculas, la
 * serie son sus primeros ocho caracteres y el número de DTE son los dieciséis
 * siguientes leídos como hexadecimal. Cuando se contrate un certificador
 * externo, se escribe otra implementación de Certificador y se cambia en
 * config/facturacion.php; las facturas ya emitidas no se tocan.
 */
class CertificadorInterno implements Certificador
{
    public function certificar(Invoice $documento): array
    {
        $uuid = strtoupper((string) Str::uuid());
        [$serie, $bloque1, $bloque2] = explode('-', $uuid);

        return [
            'estado' => 'Certificada',
            'uuid' => $uuid,
            'serie' => $serie,
            'numero' => (string) hexdec($bloque1.$bloque2),
            'certificador' => (string) config('facturacion.certificador.nombre'),
            'mensaje' => "Factura certificada con autorización {$uuid}.",
        ];
    }
}
