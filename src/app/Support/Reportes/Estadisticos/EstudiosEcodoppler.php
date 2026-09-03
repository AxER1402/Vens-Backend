<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\DopplerReport;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Estudios de Ecodöppler venoso realizados en el período.
 *
 * Es el consolidado del módulo, no el informe de un estudio: aquel describe los
 * hallazgos de un paciente, este dice cuántos se hicieron, qué segmentos
 * aparecen alterados con más frecuencia y cuáles quedaron sin conclusión.
 *
 * Solo se agregan las medidas numéricas de los segmentos. Los campos
 * descriptivos —eje profundo, perforantes, trombosis— son texto libre y
 * contarlos por coincidencia de palabras convertiría un «sin signos de
 * trombosis» en un caso positivo; se muestran tal cual en el detalle, que es
 * donde se leen.
 */
class EstudiosEcodoppler extends ReportePeriodo
{
    /**
     * Duración de reflujo a partir de la cual el segmento se considera
     * insuficiente. Medio segundo es el corte aceptado para el sistema venoso
     * superficial; se declara aquí para que el criterio del reporte se pueda
     * leer y discutir en vez de quedar escondido en una comparación.
     */
    private const REFLUJO_PATOLOGICO = 0.5;

    /** @var Collection<int, DopplerReport>|null */
    private ?Collection $estudios = null;

    private int $sobran = 0;

    public function titulo(): string
    {
        return 'Estudios de Ecodöppler';
    }

    public function archivo(): string
    {
        return 'estudios-ecodoppler';
    }

    public function subtitulo(): string
    {
        return 'Ecodöppler venoso de miembros inferiores · '.$this->periodo->etiqueta();
    }

    public function secciones(): array
    {
        $estudios = $this->estudios();

        if ($estudios->isEmpty()) {
            return $this->sinDatos('No se realizaron estudios de Ecodöppler entre las fechas indicadas.');
        }

        return array_values(array_filter([
            $this->resumen($estudios),
            $this->porSegmento($estudios),
            $this->detalle($estudios),
            $this->avisoRecorte($this->sobran),
        ]));
    }

    protected function metaExtra(): array
    {
        return [
            'Estudios' => (string) $this->estudios()->count(),
            'Pacientes' => (string) $this->estudios()->pluck('patient_id')->unique()->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Collection<int, DopplerReport>  $estudios
     * @return array<string, mixed>
     */
    private function resumen(Collection $estudios): array
    {
        $total = $estudios->count();
        $conReflujo = $estudios->filter(fn (DopplerReport $e) => $this->segmentosConReflujo($e) !== [])->count();

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen del período',
            'campos' => [
                'Estudios realizados' => (string) $total,
                'Pacientes estudiados' => (string) $estudios->pluck('patient_id')->unique()->count(),
                'Estudios por paciente' => number_format($total / max(1, $estudios->pluck('patient_id')->unique()->count()), 1, '.', ''),
                'Adjuntos a una consulta' => $this->conteoYPorcentaje($estudios->whereNotNull('clinical_history_id')->count(), $total),
                'Con conclusión redactada' => $this->conteoYPorcentaje(
                    $estudios->filter(fn (DopplerReport $e) => Formato::hayDato($e->conclusion))->count(),
                    $total
                ),
                'En borrador' => (string) $estudios->where('estado_registro', 'Borrador')->count(),
                'Con reflujo ≥ '.Formato::numero(self::REFLUJO_PATOLOGICO, 's') => $this->conteoYPorcentaje($conReflujo, $total),
                'Con anotación de trombosis' => $this->conteoYPorcentaje(
                    $estudios->filter(fn (DopplerReport $e) => Formato::hayDato($e->der_trombosis) || Formato::hayDato($e->izq_trombosis))->count(),
                    $total
                ),
            ],
        ];
    }

    /**
     * Medidas agregadas por segmento venoso, sumando los dos miembros.
     *
     * @param  Collection<int, DopplerReport>  $estudios
     * @return array<string, mixed>|null
     */
    private function porSegmento(Collection $estudios): ?array
    {
        /** @var array<string, array{evaluados: int, reflujo: int, diametros: array<int, float>, velocidades: array<int, float>, duraciones: array<int, float>}> $acumulado */
        $acumulado = [];

        foreach ($estudios as $estudio) {
            foreach (DopplerReport::LADOS as $lado) {
                foreach ($this->segmentos($estudio, $lado) as $segmento) {
                    $nombre = $segmento['nombre'];

                    $acumulado[$nombre] ??= ['evaluados' => 0, 'reflujo' => 0, 'diametros' => [], 'velocidades' => [], 'duraciones' => []];
                    $acumulado[$nombre]['evaluados']++;

                    if ($segmento['duracion'] !== null) {
                        $acumulado[$nombre]['duraciones'][] = $segmento['duracion'];

                        if ($segmento['duracion'] >= self::REFLUJO_PATOLOGICO) {
                            $acumulado[$nombre]['reflujo']++;
                        }
                    }

                    if ($segmento['diametro_max'] !== null) {
                        $acumulado[$nombre]['diametros'][] = $segmento['diametro_max'];
                    }

                    if ($segmento['velocidad'] !== null) {
                        $acumulado[$nombre]['velocidades'][] = $segmento['velocidad'];
                    }
                }
            }
        }

        if ($acumulado === []) {
            return null;
        }

        // Los tres segmentos fijos primero y en su orden anatómico; los que el
        // médico nombró, después y por frecuencia. Ordenarlo todo por cantidad
        // dejaría la tabla en distinto orden cada mes.
        uksort($acumulado, function (string $a, string $b) use ($acumulado) {
            $posicion = fn (string $nombre) => array_search($nombre, DopplerReport::SEGMENTOS_FIJOS, true);
            $pa = $posicion($a);
            $pb = $posicion($b);

            if ($pa !== false || $pb !== false) {
                return [$pa === false ? PHP_INT_MAX : $pa] <=> [$pb === false ? PHP_INT_MAX : $pb];
            }

            return [$acumulado[$b]['evaluados'], $a] <=> [$acumulado[$a]['evaluados'], $b];
        });

        $filas = [];

        foreach ($acumulado as $nombre => $datos) {
            $filas[] = [
                $nombre,
                (string) $datos['evaluados'],
                $datos['reflujo'].' ('.$this->porcentaje($datos['reflujo'], $datos['evaluados']).')',
                $this->promedio($datos['diametros'], 'mm'),
                $this->promedio($datos['velocidades'], 'cm/s'),
                $this->promedio($datos['duraciones'], 's'),
            ];
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Hallazgos por segmento venoso',
            'encabezados' => ['Segmento', 'Evaluaciones', 'Con reflujo', 'Ø máx medio', 'Velocidad media', 'Reflujo medio'],
            'filas' => $filas,
            'anchos' => [24, 14, 18, 15, 15, 14],
        ];
    }

    /**
     * @param  Collection<int, DopplerReport>  $estudios
     * @return array<string, mixed>
     */
    private function detalle(Collection $estudios): array
    {
        $filas = $estudios
            ->sortByDesc('fecha_estudio')
            ->map(function (DopplerReport $estudio) {
                $conReflujo = $this->segmentosConReflujo($estudio);

                return [
                    Formato::fecha($estudio->fecha_estudio),
                    Formato::valor($estudio->patient?->nombre),
                    $conReflujo === [] ? 'No' : $this->resumir(implode(', ', $conReflujo), 44),
                    $this->resumir($estudio->der_trombosis, 26),
                    $this->resumir($estudio->izq_trombosis, 26),
                    $this->resumir($estudio->conclusion, 60),
                ];
            })
            ->values()
            ->all();

        [$filas, $this->sobran] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Detalle de estudios',
            'encabezados' => ['Fecha', 'Paciente', 'Segmentos con reflujo', 'Trombosis MID', 'Trombosis MII', 'Conclusión'],
            'filas' => $filas,
            'anchos' => [10, 18, 22, 14, 14, 22],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lectura de los segmentos
    |--------------------------------------------------------------------------
    */

    /**
     * Segmentos informados de un miembro, ya normalizados.
     *
     * El formulario manda siempre las cinco posiciones con las últimas vacías si
     * el médico no las usó; las vacías se descartan, porque contarlas como
     * evaluaciones hundiría todos los promedios.
     *
     * @return array<int, array{nombre: string, diametro_max: ?float, velocidad: ?float, duracion: ?float}>
     */
    private function segmentos(DopplerReport $estudio, string $lado): array
    {
        $crudos = $estudio->{"{$lado}_segmentos"};

        if (! is_array($crudos)) {
            return [];
        }

        $segmentos = [];

        foreach (array_values($crudos) as $posicion => $segmento) {
            if (! is_array($segmento)) {
                continue;
            }

            $nombre = trim((string) ($segmento['nombre'] ?? DopplerReport::SEGMENTOS_FIJOS[$posicion] ?? ''));

            $medidas = [
                'diametro_max' => $this->numero($segmento['diametro_max'] ?? null),
                'velocidad' => $this->numero($segmento['velocidad'] ?? null),
                'duracion' => $this->numero($segmento['duracion'] ?? null),
            ];

            if ($nombre === '' || $medidas === array_fill_keys(array_keys($medidas), null)) {
                continue;
            }

            $segmentos[] = ['nombre' => $nombre] + $medidas;
        }

        return $segmentos;
    }

    /**
     * Nombres de los segmentos con reflujo patológico, con el miembro delante.
     *
     * @return array<int, string>
     */
    private function segmentosConReflujo(DopplerReport $estudio): array
    {
        $alterados = [];

        foreach (DopplerReport::LADOS as $lado) {
            $abrev = $lado === 'der' ? 'MID' : 'MII';

            foreach ($this->segmentos($estudio, $lado) as $segmento) {
                if ($segmento['duracion'] !== null && $segmento['duracion'] >= self::REFLUJO_PATOLOGICO) {
                    $alterados[] = "{$abrev}: {$segmento['nombre']}";
                }
            }
        }

        return $alterados;
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    private function numero(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float) $valor : null;
    }

    /**
     * @param  array<int, float>  $valores
     */
    private function promedio(array $valores, string $unidad): string
    {
        return $valores === []
            ? Formato::VACIO
            : Formato::numero(array_sum($valores) / count($valores), $unidad);
    }

    private function conteoYPorcentaje(int $cantidad, int $total): string
    {
        return $cantidad.' ('.$this->porcentaje($cantidad, max(1, $total)).')';
    }

    /**
     * @return Collection<int, DopplerReport>
     */
    private function estudios(): Collection
    {
        if ($this->estudios !== null) {
            return $this->estudios;
        }

        $consulta = DopplerReport::query()
            ->with('patient')
            ->where('activo', true)
            ->whereBetween('fecha_estudio', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()]);

        if (! empty($this->filtros['patient_id'])) {
            $consulta->where('patient_id', $this->filtros['patient_id']);
        }

        return $this->estudios = $consulta->orderBy('fecha_estudio')->get();
    }
}
