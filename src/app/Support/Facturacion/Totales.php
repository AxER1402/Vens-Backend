<?php

namespace App\Support\Facturacion;

/**
 * Las cuentas de un documento de cobro.
 *
 * Viven aparte del controlador porque las necesita también quien imprima el
 * documento o quien lo recalcule, y porque son la parte donde un error no se
 * ve: un total mal sumado se descubre cuando ya se entregó el papel.
 *
 * En Guatemala el IVA va incluido en el precio: lo que se le cobra al paciente
 * es el total, y la base y el impuesto se desglosan hacia atrás a partir de él.
 * Por eso el impuesto no se suma, se separa.
 */
class Totales
{
    /**
     * @param  array<int, array{cantidad: float|int|string, precio_unitario: float|int|string, descuento?: float|int|string|null}>  $renglones
     * @return array{subtotal: float, descuento: float, total: float, iva_monto: float, renglones: array<int, array<string, mixed>>}
     */
    public static function calcular(array $renglones, float $ivaPorcentaje = 12.0): array
    {
        $subtotal = 0.0;
        $descuentoTotal = 0.0;
        $calculados = [];

        foreach ($renglones as $renglon) {
            $cantidad = (float) $renglon['cantidad'];
            $precio = (float) $renglon['precio_unitario'];
            $descuento = (float) ($renglon['descuento'] ?? 0);

            $bruto = round($cantidad * $precio, 2);
            $neto = round($bruto - $descuento, 2);

            $subtotal += $bruto;
            $descuentoTotal += $descuento;

            $calculados[] = [
                'tipo' => $renglon['tipo'] ?? 'S',
                'descripcion' => $renglon['descripcion'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento' => $descuento,
                'total' => $neto,
            ];
        }

        $total = round($subtotal - $descuentoTotal, 2);

        // total = base + base * tasa  ⇒  base = total / (1 + tasa)
        $base = $ivaPorcentaje > 0
            ? round($total / (1 + ($ivaPorcentaje / 100)), 2)
            : $total;

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => round($descuentoTotal, 2),
            'total' => $total,
            'iva_monto' => round($total - $base, 2),
            'renglones' => $calculados,
        ];
    }
}
