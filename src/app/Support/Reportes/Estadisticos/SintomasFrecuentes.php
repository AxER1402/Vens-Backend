<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\ClinicalHistory;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Qué refieren los pacientes y qué antecedentes traen, en las consultas del
 * período.
 *
 * Todas las tablas se calculan sobre el total de consultas, no sobre el total
 * de marcas: las listas son de selección múltiple, así que los porcentajes de
 * una misma tabla suman más de 100 %. Se lee «el 62 % de las consultas refirió
 * pesadez», que es la pregunta que se hace, y no «la pesadez fue el 18 % de los
 * síntomas mencionados», que no sirve para nada clínicamente.
 */
class SintomasFrecuentes extends ReportePeriodo
{
    /**
     * Categorías del catálogo que entran en el reporte, con el epígrafe bajo el
     * que se imprime cada una.
     *
     * @var array<string, array{0: string, 1: string}> categoría => [título, encabezado de la columna]
     */
    private const LISTAS = [
        'sintomas' => ['Síntomas referidos', 'Síntoma'],
        'zonas_pierna' => ['Zonas de la pierna afectadas', 'Zona'],
        'sintomas_aumentan' => ['Factores que agravan los síntomas', 'Factor'],
        'sintomas_disminuyen' => ['Factores que alivian los síntomas', 'Factor'],
        'enfermedades' => ['Antecedentes patológicos', 'Enfermedad'],
    ];

    /** @var Collection<int, ClinicalHistory>|null */
    private ?Collection $consultas = null;

    /** @var array<string, array<string, int>>|null */
    private ?array $marcadas = null;

    public function titulo(): string
    {
        return 'Síntomas y Antecedentes Frecuentes';
    }

    public function archivo(): string
    {
        return 'sintomas-antecedentes';
    }

    public function subtitulo(): string
    {
        return 'Frecuencia sobre las consultas del período '.$this->periodo->etiqueta();
    }

    public function secciones(): array
    {
        $consultas = $this->consultas();

        if ($consultas->isEmpty()) {
            return $this->sinDatos('No se registraron consultas entre las fechas indicadas.');
        }

        $secciones = [$this->resumen($consultas)];

        foreach (self::LISTAS as $categoria => [$titulo, $encabezado]) {
            $conteo = $this->ordenarPorFrecuencia($this->marcadas()[$categoria] ?? []);

            $tabla = $this->tablaFrecuencia($titulo, $encabezado, $conteo, $consultas->count());

            if ($tabla !== null) {
                $secciones[] = $tabla;
            }
        }

        if ($otros = $this->enfermedadesOtros($consultas)) {
            $secciones[] = $otros;
        }

        return $secciones;
    }

    protected function metaExtra(): array
    {
        return [
            'Consultas' => (string) $this->consultas()->count(),
            'Pacientes' => (string) $this->consultas()->pluck('patient_id')->unique()->count(),
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
        $total = $consultas->count();
        $conSintomas = collect($this->marcadas()['sintomas'] ?? [])->sum();
        $asintomaticas = $this->marcadas()['sintomas']['Asintomática'] ?? 0;
        $familiar = $consultas->where('familiar_varices', true)->count();
        $alergias = $consultas->filter(fn (ClinicalHistory $c) => Formato::hayDato($c->alergias))->count();
        $cirugias = $consultas->filter(fn (ClinicalHistory $c) => Formato::hayDato($c->cirugias))->count();

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen del período',
            'campos' => [
                'Consultas analizadas' => (string) $total,
                'Pacientes distintos' => (string) $consultas->pluck('patient_id')->unique()->count(),
                'Consulta por enfermedad' => $this->conteoYPorcentaje($consultas->where('consulta_por', 'Enfermedad')->count(), $total),
                'Consulta por estética' => $this->conteoYPorcentaje($consultas->where('consulta_por', 'Estética')->count(), $total),
                'Síntomas marcados' => (string) $conSintomas,
                'Casos asintomáticos' => $this->conteoYPorcentaje($asintomaticas, $total),
                'Antecedente familiar de várices' => $this->conteoYPorcentaje($familiar, $total),
                'Con alergias registradas' => $this->conteoYPorcentaje($alergias, $total),
                'Con cirugías previas' => $this->conteoYPorcentaje($cirugias, $total),
                'Con perimetría registrada' => $this->conteoYPorcentaje(
                    $consultas->filter(fn (ClinicalHistory $c) => Formato::hayDato($c->perimetro_tobillo) || Formato::hayDato($c->perimetro_pantorrilla))->count(),
                    $total
                ),
            ],
        ];
    }

    /**
     * Lo que se escribió en «Otros» de la lista de enfermedades.
     *
     * Va como listado y no como tabla de frecuencias porque es texto libre: dos
     * médicos escriben la misma enfermedad de tres maneras, y contarlas como
     * valores distintos daría una tabla de unos.
     *
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>|null
     */
    private function enfermedadesOtros(Collection $consultas): ?array
    {
        $textos = $consultas
            ->map(fn (ClinicalHistory $c) => trim((string) $c->enfermedades_otros))
            ->filter(fn (string $texto) => $texto !== '')
            ->countBy()
            ->sortDesc();

        if ($textos->isEmpty()) {
            return null;
        }

        $filas = $textos->map(fn (int $veces, string $texto) => [
            $this->resumir($texto, 110),
            (string) $veces,
        ])->values()->all();

        [$filas] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Otros antecedentes anotados a mano',
            'encabezados' => ['Anotación', 'Consultas'],
            'filas' => $filas,
            'anchos' => [80, 20],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    private function conteoYPorcentaje(int $cantidad, int $total): string
    {
        return $cantidad.' ('.$this->porcentaje($cantidad, max(1, $total)).')';
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function marcadas(): array
    {
        return $this->marcadas ??= ConteoOpciones::porCategoria(
            $this->consultas()->pluck('id')->all(),
            array_keys(self::LISTAS)
        );
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
