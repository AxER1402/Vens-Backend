<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\Invoice;
use App\Support\Facturacion\DatosDocumento;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Lo que entró en el período.
 *
 * Cuenta solo documentos vigentes: un anulado sigue existiendo con su número,
 * pero no entró. Y cuenta la fecha de emisión, no la de la consulta que se
 * cobró: lo que este reporte responde es «cuánto se recibió entre estas dos
 * fechas», que es la pregunta de caja, no la de actividad clínica.
 *
 * Los totales se leen de lo guardado en cada documento y no se recalculan
 * aquí: si el IVA cambiara mañana, el reporte de un mes viejo tiene que seguir
 * diciendo lo que se cobró entonces.
 */
class IngresosPorPeriodo extends ReportePeriodo
{
    /** @var Collection<int, Invoice>|null */
    private ?Collection $documentos = null;

    private int $sobran = 0;

    public function titulo(): string
    {
        return 'Ingresos del Período';
    }

    public function archivo(): string
    {
        return 'ingresos';
    }

    public function secciones(): array
    {
        $documentos = $this->documentos();

        if ($documentos->isEmpty()) {
            return $this->sinDatos('No se registraron cobros entre las fechas indicadas.');
        }

        return array_values(array_filter([
            $this->resumen($documentos),
            $this->porDia($documentos),
            $this->porMetodoDePago($documentos),
            $this->porConcepto($documentos),
            $this->detalle($documentos),
            $this->avisoRecorte($this->sobran),
            $this->notaDeAnulados(),
        ]));
    }

    protected function metaExtra(): array
    {
        $documentos = $this->documentos();

        return [
            'Documentos' => (string) $documentos->count(),
            'Total cobrado' => DatosDocumento::quetzales($documentos->sum('total')),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, Invoice> */
    private function documentos(): Collection
    {
        if ($this->documentos !== null) {
            return $this->documentos;
        }

        $consulta = Invoice::query()
            ->with(['items', 'patient:id,nombre'])
            ->vigentes()
            ->byDateRange($this->periodo->fechaInicio(), $this->periodo->fechaFin())
            ->orderBy('fecha_emision')
            ->orderBy('numero');

        if (! empty($this->filtros['patient_id'])) {
            $consulta->byPatient((int) $this->filtros['patient_id']);
        }

        return $this->documentos = $consulta->get();
    }

    /** @return Collection<int, Invoice> */
    private function anulados(): Collection
    {
        $consulta = Invoice::query()
            ->where('estado', 'Anulada')
            ->byDateRange($this->periodo->fechaInicio(), $this->periodo->fechaFin());

        if (! empty($this->filtros['patient_id'])) {
            $consulta->byPatient((int) $this->filtros['patient_id']);
        }

        return $consulta->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Collection<int, Invoice>  $documentos
     * @return array<string, mixed>
     */
    private function resumen(Collection $documentos): array
    {
        $total = (float) $documentos->sum('total');
        $iva = (float) $documentos->sum('iva_monto');
        $descuento = (float) $documentos->sum('descuento');
        $dias = max(1, $this->periodo->dias());

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen',
            'campos' => [
                'Total cobrado' => DatosDocumento::quetzales($total),
                'Documentos emitidos' => Formato::entero($documentos->count()),
                'Base imponible' => DatosDocumento::quetzales($total - $iva),
                'IVA incluido' => DatosDocumento::quetzales($iva),
                'Descuentos otorgados' => DatosDocumento::quetzales($descuento),
                'Promedio por documento' => DatosDocumento::quetzales(
                    $documentos->count() > 0 ? $total / $documentos->count() : 0
                ),
                'Promedio por día del período' => DatosDocumento::quetzales($total / $dias),
                'Facturas / recibos' => sprintf(
                    '%d / %d',
                    $documentos->where('tipo', Invoice::TIPO_FACTURA)->count(),
                    $documentos->where('tipo', Invoice::TIPO_RECIBO)->count()
                ),
            ],
        ];
    }

    /**
     * Lo cobrado día por día. Es la tabla que se busca cuando la pregunta es
     * «cuánto entró hoy» o «cuánto entró el martes».
     *
     * @param  Collection<int, Invoice>  $documentos
     * @return array<string, mixed>
     */
    private function porDia(Collection $documentos): array
    {
        $filas = [];

        foreach ($documentos->groupBy(fn (Invoice $d) => $d->fecha_emision->toDateString()) as $dia => $delDia) {
            $filas[] = [
                Formato::fecha($dia),
                Formato::entero($delDia->count()),
                DatosDocumento::quetzales($delDia->sum('total')),
            ];
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Cobros por día',
            'encabezados' => ['Fecha', 'Documentos', 'Total cobrado'],
            'filas' => $filas,
            'anchos' => [40, 25, 35],
        ];
    }

    /**
     * @param  Collection<int, Invoice>  $documentos
     * @return array<string, mixed>|null
     */
    private function porMetodoDePago(Collection $documentos): ?array
    {
        $total = (float) $documentos->sum('total');
        $filas = [];

        foreach ($documentos->groupBy('metodo_pago') as $metodo => $delMetodo) {
            $suma = (float) $delMetodo->sum('total');

            $filas[] = [
                Formato::valor($metodo),
                Formato::entero($delMetodo->count()),
                DatosDocumento::quetzales($suma),
                $this->porcentaje($suma, $total),
            ];
        }

        usort($filas, fn ($a, $b) => strcmp($b[2], $a[2]));

        return [
            'tipo' => 'tabla',
            'titulo' => 'Cómo se pagó',
            'encabezados' => ['Método de pago', 'Documentos', 'Total', '% del período'],
            'filas' => $filas,
            'anchos' => [35, 18, 27, 20],
        ];
    }

    /**
     * Qué se cobró, sumando los renglones de todos los documentos. Responde
     * de dónde sale el dinero, que no es lo mismo que cuánto entró.
     *
     * @param  Collection<int, Invoice>  $documentos
     * @return array<string, mixed>|null
     */
    private function porConcepto(Collection $documentos): ?array
    {
        $conceptos = [];

        foreach ($documentos as $documento) {
            foreach ($documento->items as $item) {
                $clave = trim((string) $item->descripcion);

                $conceptos[$clave] ??= ['cantidad' => 0.0, 'total' => 0.0];
                $conceptos[$clave]['cantidad'] += (float) $item->cantidad;
                $conceptos[$clave]['total'] += (float) $item->total;
            }
        }

        if ($conceptos === []) {
            return null;
        }

        uasort($conceptos, fn ($a, $b) => $b['total'] <=> $a['total']);

        $filas = [];

        foreach ($conceptos as $descripcion => $datos) {
            $filas[] = [
                Formato::valor($descripcion),
                Formato::numero($datos['cantidad']),
                DatosDocumento::quetzales($datos['total']),
            ];
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'De dónde sale',
            'encabezados' => ['Concepto', 'Cantidad', 'Total cobrado'],
            'filas' => $this->recortar($filas),
            'anchos' => [55, 18, 27],
        ];
    }

    /**
     * @param  Collection<int, Invoice>  $documentos
     * @return array<string, mixed>
     */
    private function detalle(Collection $documentos): array
    {
        $filas = [];

        foreach ($documentos as $documento) {
            $filas[] = [
                $documento->correlativo,
                Formato::fecha($documento->fecha_emision),
                Formato::valor($documento->patient?->nombre ?? $documento->nombre_receptor),
                $documento->tipo === Invoice::TIPO_FACTURA ? 'Factura' : 'Recibo',
                Formato::valor($documento->metodo_pago),
                DatosDocumento::quetzales($documento->total),
            ];
        }

        $recortadas = $this->recortar($filas);
        $this->sobran = count($filas) - count($recortadas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Detalle de documentos',
            'encabezados' => ['Documento', 'Fecha', 'Paciente', 'Tipo', 'Pago', 'Total'],
            'filas' => $recortadas,
            'anchos' => [12, 14, 30, 12, 16, 16],
        ];
    }

    /**
     * Los anulados no suman, pero callarlos deja al lector preguntándose por
     * los números que faltan en el correlativo.
     *
     * @return array<string, mixed>|null
     */
    private function notaDeAnulados(): ?array
    {
        $anulados = $this->anulados();

        if ($anulados->isEmpty()) {
            return null;
        }

        return [
            'tipo' => 'texto',
            'titulo' => 'Documentos anulados',
            'texto' => sprintf(
                'En el período se anularon %s documento(s) por un valor de %s. No están incluidos en ninguna de '
                . 'las cifras anteriores; se mencionan porque ocupan su número en el correlativo.',
                Formato::entero($anulados->count()),
                DatosDocumento::quetzales($anulados->sum('total'))
            ),
        ];
    }
}
