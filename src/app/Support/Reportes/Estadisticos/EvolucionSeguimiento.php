<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\ClinicalHistory;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Cómo respondieron los pacientes al tratamiento durante el período.
 *
 * Además de las dos distribuciones —evolución y estado general— el reporte
 * levanta una lista nominal de los casos que no van bien. Es lo que se usa de
 * verdad: un porcentaje de empeoramiento no permite llamar a nadie, y el
 * seguimiento de un paciente que va a peor no puede depender de que alguien se
 * acuerde de revisar el expediente.
 */
class EvolucionSeguimiento extends ReportePeriodo
{
    /** Escala de evolución, del mejor al peor desenlace. */
    private const EVOLUCION = ['Mejoría', 'Igual', 'Empeoramiento'];

    /** Cierres de consulta, del más tranquilo al que exige actuar. */
    private const ESTADOS = [
        'Respuesta satisfactoria',
        'Requiere nuevas sesiones',
        'Requiere cirugía',
        'Sospecha de complicación',
    ];

    /** Cierres que obligan a un seguimiento nominal. */
    private const ALERTA = ['Requiere cirugía', 'Sospecha de complicación'];

    /** @var Collection<int, ClinicalHistory>|null */
    private ?Collection $consultas = null;

    public function titulo(): string
    {
        return 'Evolución y Seguimiento';
    }

    public function archivo(): string
    {
        return 'evolucion-seguimiento';
    }

    public function secciones(): array
    {
        $consultas = $this->consultas();

        if ($consultas->isEmpty()) {
            return $this->sinDatos('No se registraron consultas entre las fechas indicadas.');
        }

        $observaciones = ConteoOpciones::porCategoria($consultas->pluck('id')->all(), ['observaciones'])['observaciones'] ?? [];

        return array_values(array_filter([
            $this->resumen($consultas),
            $this->distribucion('Evolución clínica', 'Evolución', 'evolucion', self::EVOLUCION),
            $this->distribucion('Estado general al cierre de la consulta', 'Estado', 'estado_general', self::ESTADOS),
            $this->tablaFrecuencia('Observaciones del procedimiento', 'Observación', $this->ordenarPorFrecuencia($observaciones), $consultas->count()),
            $this->casosDeAlerta($consultas),
            $this->seguimiento($consultas),
        ]));
    }

    protected function metaExtra(): array
    {
        return [
            'Consultas' => (string) $this->consultas()->count(),
            'Casos de alerta' => (string) $this->deAlerta($this->consultas())->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>
     */
    private function resumen(Collection $consultas): array
    {
        $conEvolucion = $consultas->filter(fn (ClinicalHistory $c) => Formato::hayDato($c->evolucion));
        $base = max(1, $conEvolucion->count());
        $recurrentes = $consultas->groupBy('patient_id')->filter(fn (Collection $g) => $g->count() > 1);

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen del período',
            'campos' => [
                'Consultas del período' => (string) $consultas->count(),
                'Con evolución registrada' => $conEvolucion->count().' ('.$this->porcentaje($conEvolucion->count(), $consultas->count()).')',
                'Mejoría' => $conEvolucion->where('evolucion', 'Mejoría')->count().' ('.$this->porcentaje($conEvolucion->where('evolucion', 'Mejoría')->count(), $base).')',
                'Sin cambios' => $conEvolucion->where('evolucion', 'Igual')->count().' ('.$this->porcentaje($conEvolucion->where('evolucion', 'Igual')->count(), $base).')',
                'Empeoramiento' => $conEvolucion->where('evolucion', 'Empeoramiento')->count().' ('.$this->porcentaje($conEvolucion->where('evolucion', 'Empeoramiento')->count(), $base).')',
                'Requieren cirugía' => (string) $consultas->where('estado_general', 'Requiere cirugía')->count(),
                'Sospecha de complicación' => (string) $consultas->where('estado_general', 'Sospecha de complicación')->count(),
                'Pacientes con más de una consulta' => (string) $recurrentes->count(),
            ],
        ];
    }

    /**
     * Distribución de una columna de enumerado, en el orden de la escala.
     *
     * @param  array<int, string>  $escala
     * @return array<string, mixed>|null
     */
    private function distribucion(string $titulo, string $encabezado, string $columna, array $escala): ?array
    {
        $consultas = $this->consultas();
        $registradas = $consultas->filter(fn (ClinicalHistory $c) => Formato::hayDato($c->{$columna}));

        if ($registradas->isEmpty()) {
            return null;
        }

        $conteo = [];

        foreach ($escala as $valor) {
            $cantidad = $registradas->where($columna, $valor)->count();

            if ($cantidad > 0) {
                $conteo[$valor] = $cantidad;
            }
        }

        // El porcentaje se calcula sobre las consultas que sí llevan el dato:
        // dividir entre todas convertiría los campos sin llenar en un desenlace.
        return $this->tablaFrecuencia($titulo, $encabezado, $conteo, $registradas->count());
    }

    /**
     * Consultas que exigen seguimiento, con nombre y apellido.
     *
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>|null
     */
    private function casosDeAlerta(Collection $consultas): ?array
    {
        $alerta = $this->deAlerta($consultas);

        if ($alerta->isEmpty()) {
            return null;
        }

        $filas = $alerta
            ->sortByDesc('fecha_consulta')
            ->map(fn (ClinicalHistory $c) => [
                Formato::fecha($c->fecha_consulta),
                Formato::valor($c->patient?->nombre),
                Formato::valor($c->ceap_c),
                Formato::valor($c->evolucion),
                Formato::valor($c->estado_general),
                $this->resumir($c->notas, 60),
            ])
            ->values()
            ->all();

        [$filas] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Casos que requieren seguimiento',
            'encabezados' => ['Fecha', 'Paciente', 'CEAP', 'Evolución', 'Estado general', 'Notas'],
            'filas' => $filas,
            'anchos' => [11, 22, 8, 14, 20, 25],
        ];
    }

    /**
     * Pacientes que vinieron más de una vez dentro del período, con el desenlace
     * de su última consulta.
     *
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>|null
     */
    private function seguimiento(Collection $consultas): ?array
    {
        $recurrentes = $consultas->groupBy('patient_id')->filter(fn (Collection $g) => $g->count() > 1);

        if ($recurrentes->isEmpty()) {
            return null;
        }

        $filas = $recurrentes
            ->map(function (Collection $grupo) {
                $ordenadas = $grupo->sortBy('fecha_consulta')->values();
                $ultima = $ordenadas->last();

                return [
                    'orden' => [-$grupo->count(), mb_strtolower((string) $ultima->patient?->nombre)],
                    'fila' => [
                        Formato::valor($ultima->patient?->nombre),
                        (string) $grupo->count(),
                        Formato::fecha($ordenadas->first()->fecha_consulta),
                        Formato::fecha($ultima->fecha_consulta),
                        Formato::valor($ultima->evolucion),
                        Formato::valor($ultima->estado_general),
                    ],
                ];
            })
            ->sortBy('orden')
            ->pluck('fila')
            ->values()
            ->all();

        [$filas, $sobran] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Pacientes en seguimiento durante el período',
            'encabezados' => ['Paciente', 'Consultas', 'Primera', 'Última', 'Evolución', 'Estado general'],
            'filas' => $filas,
            'anchos' => [27, 11, 13, 13, 15, 21],
            'extra' => $this->avisoRecorte($sobran),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return Collection<int, ClinicalHistory>
     */
    private function deAlerta(Collection $consultas): Collection
    {
        return $consultas->filter(fn (ClinicalHistory $c) => $c->evolucion === 'Empeoramiento'
            || in_array($c->estado_general, self::ALERTA, true));
    }

    /**
     * @return Collection<int, ClinicalHistory>
     */
    private function consultas(): Collection
    {
        if ($this->consultas !== null) {
            return $this->consultas;
        }

        $consulta = ClinicalHistory::query()
            ->with('patient')
            ->where('activo', true)
            ->whereBetween('fecha_consulta', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()]);

        if (! empty($this->filtros['patient_id'])) {
            $consulta->where('patient_id', $this->filtros['patient_id']);
        }

        return $this->consultas = $consulta->get();
    }
}
