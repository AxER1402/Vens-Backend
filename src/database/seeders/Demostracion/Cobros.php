<?php

namespace Database\Seeders\Demostracion;

use Illuminate\Support\Carbon;

/**
 * Los cobros que no nacen de una consulta.
 *
 * El módulo de facturación hace más cosas de las que se ven cobrando visitas:
 * vende material, cobra una curación de enfermería, emite factura con NIT
 * cuando el paciente la pide, la emite a nombre de quien paga y no de quien se
 * atiende, y anula un documento que salió mal. Nada de eso aparece si todos los
 * documentos de muestra son el recibo de una consulta, y es justo lo que la
 * institución va a preguntar.
 *
 * Cada entrada nombra a su paciente; el seeder los busca ya creados. Las fechas
 * se expresan en días hacia atrás desde hoy, como el resto de los datos de
 * muestra.
 *
 * Sobre las facturas: salen «Pendiente» de certificar porque es la verdad del
 * sistema hoy —no hay certificador contratado— y porque el propio
 * CertificadorPendiente se niega a inventar un número de autorización. Una
 * factura de muestra con un UUID falso se vería certificada sin estarlo, que es
 * exactamente lo que ese código evita. Se imprimen con la leyenda
 * «SIN CERTIFICAR» encima, que es lo que se verá hasta que haya API.
 *
 * Los NIT son de pacientes y empresas inventados.
 */
class Cobros
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sueltos(Carbon $hoy): array
    {
        return [
            // ── Venta de material sin consulta ───────────────────────────────
            // La paciente vuelve por el segundo par de medias cuando se le gasta
            // el primero. No hay visita que cobrar, solo el material.
            [
                'paciente' => 'Marta Elena Girón de Ramírez',
                'fecha' => self::dia($hoy, 92),
                'metodo_pago' => 'Efectivo',
                'observaciones' => 'Reposición del par entregado en la primera consulta.',
                'renglones' => [
                    ['tipo' => 'B', 'descripcion' => 'Media compresiva hasta la rodilla 20-30 mmHg', 'cantidad' => 1, 'precio_unitario' => 385.00],
                ],
            ],

            // ── Curación de enfermería entre controles ───────────────────────
            // La úlcera se cura cada 72 horas y no todas las curaciones son una
            // consulta con el médico.
            [
                'paciente' => 'Julio César Archila Morales',
                'fecha' => self::dia($hoy, 75),
                'metodo_pago' => 'Efectivo',
                'renglones' => [
                    ['descripcion' => 'Curación de úlcera y recambio de vendaje (enfermería)', 'cantidad' => 2, 'precio_unitario' => 150.00],
                    ['tipo' => 'B', 'descripcion' => 'Apósito de hidrofibra con plata', 'cantidad' => 2, 'precio_unitario' => 95.00],
                ],
            ],

            // ── Factura con NIT del paciente ─────────────────────────────────
            // Quien la pide es quien la necesita para su contabilidad. Sale sin
            // certificar, que es como salen hoy todas.
            [
                'paciente' => 'Julio César Archila Morales',
                'fecha' => self::dia($hoy, 40),
                'tipo' => 'factura',
                'nit' => '2485913-6',
                'metodo_pago' => 'Transferencia',
                'observaciones' => 'El paciente solicita factura para reembolso de su seguro médico.',
                'renglones' => [
                    ['tipo' => 'B', 'descripcion' => 'Vendaje multicapa de compresión', 'cantidad' => 3, 'precio_unitario' => 420.00],
                ],
            ],

            // ── Factura a nombre de quien paga, no de quien se atiende ───────
            // El recibo tiene que decir las dos cosas: la empresa que pagó y el
            // paciente que se atendió.
            [
                'paciente' => 'Héctor Fernando Maldonado Paz',
                'fecha' => self::dia($hoy, 33),
                'tipo' => 'factura',
                'nit' => '5619234-1',
                'receptor' => 'Deportes y Montaña, Sociedad Anónima',
                'direccion' => '6a calle 2-14, zona 1, Santa Cruz del Quiché',
                'metodo_pago' => 'Cheque',
                'observaciones' => 'Estudio cubierto por el patrono del paciente según convenio de medicina de empresa.',
                'renglones' => [
                    ['descripcion' => 'Ecodöppler venoso de miembros inferiores (bilateral)', 'cantidad' => 1, 'precio_unitario' => 650.00],
                ],
            ],

            // ── Cobro con descuento ──────────────────────────────────────────
            // El descuento se registra renglón a renglón, así queda dicho sobre
            // qué se aplicó y no como una rebaja suelta al final.
            [
                'paciente' => 'Rosa María Tzoc Xiloj',
                'fecha' => self::dia($hoy, 42),
                'metodo_pago' => 'Efectivo',
                'observaciones' => 'Descuento del programa de apoyo de la clínica, autorizado por la dirección.',
                'renglones' => [
                    ['descripcion' => 'Curación y control de piel en enfermería', 'cantidad' => 1, 'precio_unitario' => 150.00, 'descuento' => 50.00],
                    ['tipo' => 'B', 'descripcion' => 'Emoliente con urea al 10 %, 200 ml', 'cantidad' => 2, 'precio_unitario' => 110.00, 'descuento' => 60.00],
                ],
            ],
        ];
    }

    private static function dia(Carbon $hoy, int $atras): string
    {
        return $hoy->copy()->subDays($atras)->toDateString();
    }
}
