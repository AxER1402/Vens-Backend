<?php

namespace App\Support\Facturacion;

use App\Models\Invoice;

/**
 * La costura por donde entrará la certificación electrónica.
 *
 * En Guatemala una factura no vale por emitirse: hay que mandarla a un
 * certificador autorizado, que la firma y devuelve el número de autorización
 * de la SAT. Esa API todavía no está contratada, así que hoy existe una sola
 * implementación —la que no certifica nada— y el documento queda en
 * «Pendiente» esperando.
 *
 * Está declarada desde ahora, y no cuando llegue la API, por una razón
 * concreta: define qué le pide el sistema al certificador. El día que se
 * contrate, lo que hay que escribir es otra clase que implemente esto y
 * cambiar dónde se resuelve; no hay que ir a buscar por el código dónde se
 * emiten facturas ni migrar las que ya se emitieron.
 */
interface Certificador
{
    /**
     * Manda el documento a certificar y devuelve cómo quedó.
     *
     * @return array{estado: string, uuid: string|null, serie: string|null, numero: string|null, certificador: string|null, mensaje: string|null}
     */
    public function certificar(Invoice $documento): array;
}
