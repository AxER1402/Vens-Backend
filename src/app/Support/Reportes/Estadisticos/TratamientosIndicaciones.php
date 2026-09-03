<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\ClinicalHistory;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Qué se aplicó y qué se recetó en las consultas del período.
 *
 * El tratamiento vive en dos planos que el reporte no mezcla: la sesión de
 * escleroterapia, que son medidas (concentración, forma y volumen), y las
 * indicaciones, que son una lista marcada más el medicamento concreto escrito
 * en `indicaciones_detalle`. Marcar «Venotónico» dice el tipo de tratamiento; el
 * detalle dice cuál, y son dos tablas distintas porque responden a dos
 * preguntas distintas: qué se prescribe y con qué se prescribe.
 */
class TratamientosIndicaciones extends ReportePeriodo
{
    /** @var Collection<int, ClinicalHistory>|null */
    private ?Collection $consultas = null;

    public function titulo(): string
    {
        return 'Tratamientos e Indicaciones';
    }

    public function archivo(): string
    {
        return 'tratamientos-indicaciones';
    }

    public function secciones(): array
    {
        $consultas = $this->consultas();

        if ($consultas->isEmpty()) {
            return $this->sinDatos('No se registraron consultas entre las fechas indicadas.');
        }

        $marcadas = ConteoOpciones::porCategoria($consultas->pluck('id')->all(), ['indicaciones', 'tx_zonas']);

        return array_values(array_filter([
            $this->resumen($consultas),
            $this->escleroterapia($consultas),
            $this->tablaFrecuencia('Zonas tratadas', 'Zona', $this->ordenarPorFrecuencia($marcadas['tx_zonas'] ?? []), $consultas->count()),
            $this->tablaFrecuencia('Indicaciones prescritas', 'Indicación', $this->ordenarPorFrecuencia($marcadas['indicaciones'] ?? []), $consultas->count()),
            $this->medicamentos($consultas),
            $this->indicacionesOtros($consultas),
        ]));
    }

    protected function metaExtra(): array
    {
        return [
            'Consultas' => (string) $this->consultas()->count(),
            'Con escleroterapia' => (string) $this->conEscleroterapia()->count(),
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
        $sesiones = $this->conEscleroterapia();
        $volumenes = $sesiones->pluck('esclero_volumen')->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v);
        $concentraciones = $sesiones->pluck('esclero_concentracion')->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v);

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen del período',
            'campos' => [
                'Consultas del período' => (string) $consultas->count(),
                'Pacientes tratados' => (string) $sesiones->pluck('patient_id')->unique()->count(),
                'Sesiones de escleroterapia' => $sesiones->count().' ('.$this->porcentaje($sesiones->count(), $consultas->count()).')',
                'Volumen total aplicado' => $volumenes->isNotEmpty() ? Formato::numero($volumenes->sum(), 'ml') : Formato::VACIO,
                'Volumen medio por sesión' => $volumenes->isNotEmpty() ? Formato::numero($volumenes->avg(), 'ml') : Formato::VACIO,
                'Concentración media' => $concentraciones->isNotEmpty() ? Formato::numero($concentraciones->avg(), '%') : Formato::VACIO,
                'Concentración mínima / máxima' => $concentraciones->isNotEmpty()
                    ? Formato::numero($concentraciones->min(), '%').' / '.Formato::numero($concentraciones->max(), '%')
                    : Formato::VACIO,
                'Consultas con indicaciones' => (string) $consultas
                    ->filter(fn (ClinicalHistory $c) => Formato::hayDato($c->indicaciones_detalle) || Formato::hayDato($c->indicaciones_otros))
                    ->count(),
            ],
        ];
    }

    /**
     * Sesiones agrupadas por forma del esclerosante.
     *
     * La espuma y el líquido no se dosifican igual, así que promediar el volumen
     * de los dos juntos no dice nada: cada forma lleva su fila con su propio
     * volumen y su propia concentración.
     *
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>|null
     */
    private function escleroterapia(Collection $consultas): ?array
    {
        $sesiones = $this->conEscleroterapia();

        if ($sesiones->isEmpty()) {
            return null;
        }

        $filas = $sesiones
            ->groupBy(fn (ClinicalHistory $c) => Formato::hayDato($c->esclero_forma) ? $c->esclero_forma : 'Sin forma registrada')
            ->map(function (Collection $grupo, string $forma) use ($sesiones) {
                $volumenes = $grupo->pluck('esclero_volumen')->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v);
                $concentraciones = $grupo->pluck('esclero_concentracion')->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v);

                return [
                    'orden' => -$grupo->count(),
                    'fila' => [
                        $forma,
                        (string) $grupo->count(),
                        $this->porcentaje($grupo->count(), $sesiones->count()),
                        $concentraciones->isNotEmpty() ? Formato::numero($concentraciones->avg(), '%') : Formato::VACIO,
                        $volumenes->isNotEmpty() ? Formato::numero($volumenes->avg(), 'ml') : Formato::VACIO,
                        $volumenes->isNotEmpty() ? Formato::numero($volumenes->sum(), 'ml') : Formato::VACIO,
                    ],
                ];
            })
            ->sortBy('orden')
            ->pluck('fila')
            ->values()
            ->all();

        return [
            'tipo' => 'tabla',
            'titulo' => 'Escleroterapia por forma del esclerosante',
            'encabezados' => ['Forma', 'Sesiones', '%', 'Concentración media', 'Volumen medio', 'Volumen total'],
            'filas' => $filas,
            'anchos' => [20, 12, 12, 20, 18, 18],
        ];
    }

    /**
     * Medicamentos escritos en el detalle de cada indicación.
     *
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>|null
     */
    private function medicamentos(Collection $consultas): ?array
    {
        $conteo = [];

        foreach ($consultas as $consulta) {
            $detalle = $consulta->indicaciones_detalle;

            if (! is_array($detalle)) {
                continue;
            }

            foreach ($detalle as $indicacion => $medicamento) {
                $texto = trim((string) $medicamento);

                if ($texto === '') {
                    continue;
                }

                $clave = $indicacion.'||'.$texto;
                $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
            }
        }

        if ($conteo === []) {
            return null;
        }

        arsort($conteo);

        $filas = [];

        foreach ($conteo as $clave => $veces) {
            [$indicacion, $medicamento] = explode('||', $clave, 2);

            $filas[] = [
                Formato::valor($indicacion),
                $this->resumir($medicamento, 60),
                (string) $veces,
                $this->porcentaje($veces, $consultas->count()),
            ];
        }

        [$filas] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Medicamentos indicados',
            'encabezados' => ['Indicación', 'Medicamento', 'Consultas', '%'],
            'filas' => $filas,
            'anchos' => [24, 46, 15, 15],
        ];
    }

    /**
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>|null
     */
    private function indicacionesOtros(Collection $consultas): ?array
    {
        $textos = $consultas
            ->map(fn (ClinicalHistory $c) => trim((string) $c->indicaciones_otros))
            ->filter(fn (string $texto) => $texto !== '')
            ->countBy()
            ->sortDesc();

        if ($textos->isEmpty()) {
            return null;
        }

        $filas = $textos->map(fn (int $veces, string $texto) => [$this->resumir($texto, 110), (string) $veces])
            ->values()
            ->all();

        [$filas] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Otras indicaciones anotadas a mano',
            'encabezados' => ['Indicación', 'Consultas'],
            'filas' => $filas,
            'anchos' => [80, 20],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    /**
     * Consultas en las que hubo sesión: basta con que se registrara una de las
     * tres medidas, porque el formulario permite anotar la forma sin el volumen
     * y al revés.
     *
     * @return Collection<int, ClinicalHistory>
     */
    private function conEscleroterapia(): Collection
    {
        return $this->consultas()->filter(fn (ClinicalHistory $c) => Formato::hayDato($c->esclero_forma)
            || Formato::hayDato($c->esclero_concentracion)
            || Formato::hayDato($c->esclero_volumen));
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
            ->where('activo', true)
            ->whereBetween('fecha_consulta', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()]);

        if (! empty($this->filtros['patient_id'])) {
            $consulta->where('patient_id', $this->filtros['patient_id']);
        }

        return $this->consultas = $consulta->get();
    }
}
