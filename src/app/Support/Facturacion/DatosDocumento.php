<?php

namespace App\Support\Facturacion;

use App\Models\Invoice;
use App\Support\Reportes\Ficha;
use App\Support\Reportes\Formato;

/**
 * El documento de cobro imprimible: lo que se le entrega al paciente.
 *
 * Se arma con las mismas piezas que los informes clínicos —el mismo membrete,
 * el mismo pie, los mismos bloques— porque lo que sale de la clínica tiene que
 * verse como una sola cosa, y porque así el recibo hereda gratis la numeración
 * de páginas y la emisión en PDF y Word que ya existían.
 */
class DatosDocumento
{
    public function __construct(private readonly Invoice $documento)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function construir(): array
    {
        $esFactura = $this->documento->tipo === Invoice::TIPO_FACTURA;
        $anulado = $this->documento->estado === 'Anulada';
        $sinCertificar = $esFactura && $this->documento->fel_estado !== 'Certificada';

        return [
            'titulo' => $esFactura ? 'Factura' : 'Recibo de pago',
            'subtitulo' => "No. {$this->documento->correlativo}",
            'archivo' => $esFactura ? 'factura' : 'recibo',

            // Un documento anulado o una factura sin certificar se pueden
            // imprimir —hacen falta para el archivo— pero no pueden salir a la
            // calle con aspecto de documento válido.
            'marca_agua' => $anulado ? 'ANULADO' : ($sinCertificar ? 'SIN CERTIFICAR' : null),

            'paciente' => Ficha::paciente($this->documento->patient),
            'meta' => $this->meta(),
            'secciones' => array_values(array_filter([
                $this->avisoDeEstado($anulado, $sinCertificar),
                $this->receptor(),
                $this->renglones(),
                $this->cuentas(),
                $this->observaciones(),
            ])),
            'firma' => Ficha::firma($this->documento->creator),
            'nombre_archivo_base' => $this->documento->patient?->nombre,
            'fecha_archivo' => $this->documento->fecha_emision,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function meta(): array
    {
        $emisor = config('facturacion.emisor');

        return array_filter([
            'Documento' => $this->documento->correlativo,
            'Fecha de emisión' => Formato::fecha($this->documento->fecha_emision),
            'Método de pago' => Formato::valor($this->documento->metodo_pago),
            'Emite' => Formato::valor($emisor['nombre'] ?? null),
            'NIT emisor' => Formato::hayDato($emisor['nit'] ?? null) ? $emisor['nit'] : null,
        ]);
    }

    /**
     * El aviso va arriba del todo y no al pie: quien recibe el papel tiene que
     * saber qué tiene en la mano antes de leer los montos.
     *
     * @return array<string, mixed>|null
     */
    private function avisoDeEstado(bool $anulado, bool $sinCertificar): ?array
    {
        if ($anulado) {
            return [
                'tipo' => 'texto',
                'titulo' => 'Documento anulado',
                'texto' => 'Este documento fue anulado el '
                    . Formato::fecha($this->documento->updated_at)
                    . ' y no acredita ningún pago. Motivo: '
                    . Formato::valor($this->documento->motivo_anulacion) . '.',
            ];
        }

        if ($sinCertificar) {
            return [
                'tipo' => 'texto',
                'titulo' => 'Pendiente de certificar',
                'texto' => 'Esta factura está registrada en el sistema pero todavía no ha sido certificada '
                    . 'ante la SAT, así que no tiene validez tributaria. '
                    . Formato::valor($this->documento->fel_mensaje),
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function receptor(): array
    {
        return [
            'tipo' => 'campos',
            'titulo' => 'Datos del receptor',
            'campos' => array_filter([
                'Nombre' => Formato::valor($this->documento->nombre_receptor),
                'NIT' => Formato::valor($this->documento->nit_receptor),
                'Dirección' => Formato::hayDato($this->documento->direccion_receptor)
                    ? Formato::valor($this->documento->direccion_receptor)
                    : null,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function renglones(): array
    {
        $filas = [];

        foreach ($this->documento->items as $item) {
            $filas[] = [
                Formato::valor($item->descripcion),
                $this->cantidad($item->cantidad),
                self::quetzales($item->precio_unitario),
                (float) $item->descuento > 0 ? self::quetzales($item->descuento) : Formato::VACIO,
                self::quetzales($item->total),
            ];
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Detalle',
            'encabezados' => ['Descripción', 'Cantidad', 'Precio unitario', 'Descuento', 'Total'],
            'filas' => $filas,
            'anchos' => [40, 12, 17, 14, 17],
        ];
    }

    /**
     * El IVA se anuncia como incluido y no como una línea que se suma: leerlo
     * debajo del total invita a sumarlo otra vez.
     *
     * @return array<string, mixed>
     */
    private function cuentas(): array
    {
        $iva = (float) $this->documento->iva_porcentaje;

        return [
            'tipo' => 'campos',
            'titulo' => 'Totales',
            'campos' => array_filter([
                'Subtotal' => self::quetzales($this->documento->subtotal),
                'Descuento' => (float) $this->documento->descuento > 0
                    ? '− ' . self::quetzales($this->documento->descuento)
                    : null,
                'Total a pagar' => self::quetzales($this->documento->total),
                "IVA {$this->porcentaje($iva)} incluido en el total"
                    => self::quetzales($this->documento->iva_monto),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function observaciones(): ?array
    {
        if (! Formato::hayDato($this->documento->observaciones)) {
            return null;
        }

        return [
            'tipo' => 'texto',
            'titulo' => 'Observaciones',
            'texto' => Formato::valor($this->documento->observaciones),
        ];
    }

    /** Cantidades enteras sin decimales: «2» y no «2.00». */
    private function cantidad(mixed $valor): string
    {
        $numero = (float) $valor;

        return floor($numero) === $numero
            ? (string) (int) $numero
            : number_format($numero, 2, '.', ',');
    }

    private function porcentaje(float $valor): string
    {
        return floor($valor) === $valor
            ? ((string) (int) $valor) . '%'
            : number_format($valor, 2, '.', ',') . '%';
    }

    public static function quetzales(mixed $monto): string
    {
        return 'Q ' . number_format((float) $monto, 2, '.', ',');
    }
}
