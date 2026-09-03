<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\ClinicalHistory;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Distribución de la clasificación CEAP en las consultas del período.
 *
 * CEAP tiene cuatro ejes y el sistema los guarda en dos sitios distintos: la
 * clase clínica (C) se escribe a mano en `ceap_c` porque admite sufijo —C2a,
 * C4s—, y los otros tres se marcan del catálogo. El reporte los presenta juntos
 * porque en la consulta se leen juntos, pero cuenta cada eje sobre su propia
 * base: una consulta puede no llevar clase clínica y sí etiología, y dividir
 * todo entre el mismo total daría porcentajes que no suman.
 */
class DiagnosticosCeap extends ReportePeriodo
{
    /**
     * Clases clínicas del CEAP con lo que significan. Se imprimen aunque el
     * período no tenga ninguna: un cero en C6 —úlcera activa— es un dato que se
     * quiere ver, no una fila que sobra.
     *
     * @var array<string, string>
     */
    private const CLASES = [
        'C0' => 'Sin signos visibles ni palpables',
        'C1' => 'Telangiectasias o venas reticulares',
        'C2' => 'Venas varicosas',
        'C3' => 'Edema',
        'C4' => 'Cambios cutáneos (pigmentación, eczema, lipodermatoesclerosis)',
        'C5' => 'Úlcera venosa cicatrizada',
        'C6' => 'Úlcera venosa activa',
    ];

    /** Los tres ejes que se marcan del catálogo, agrupados como se leen. */
    private const EJES = [
        'Etiología (E)' => ['Primaria', 'Secundaria'],
        'Anatomía (A)' => ['Superficial', 'Profunda', 'Perforantes', 'Mixtas'],
        'Fisiopatología (P)' => ['Reflujo', 'Obstrucción'],
    ];

    /** @var Collection<int, ClinicalHistory>|null */
    private ?Collection $consultas = null;

    public function titulo(): string
    {
        return 'Diagnósticos CEAP';
    }

    public function archivo(): string
    {
        return 'diagnosticos-ceap';
    }

    public function subtitulo(): string
    {
        return 'Clasificación clínica, etiológica, anatómica y fisiopatológica · '.$this->periodo->etiqueta();
    }

    public function secciones(): array
    {
        $consultas = $this->consultas();

        if ($consultas->isEmpty()) {
            return $this->sinDatos('No se registraron consultas entre las fechas indicadas.');
        }

        return array_values(array_filter(array_merge(
            [$this->resumen($consultas), $this->claseClinica($consultas)],
            $this->ejesDelCatalogo($consultas),
            [$this->ubicacion($consultas)],
        )));
    }

    protected function metaExtra(): array
    {
        return [
            'Consultas' => (string) $this->consultas()->count(),
            'Con clase C' => (string) $this->clasificadas()->count(),
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
        $clasificadas = $this->clasificadas();
        $conteo = $this->conteoClases();
        $avanzadas = ($conteo['C4'] ?? 0) + ($conteo['C5'] ?? 0) + ($conteo['C6'] ?? 0);

        arsort($conteo);
        $predominante = array_key_first(array_filter($conteo));

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen del período',
            'campos' => [
                'Consultas del período' => (string) $consultas->count(),
                'Con clase clínica registrada' => $clasificadas->count().' ('.$this->porcentaje($clasificadas->count(), $consultas->count()).')',
                'Clase predominante' => $predominante
                    ? $predominante.' · '.self::CLASES[$predominante]
                    : Formato::VACIO,
                'Enfermedad avanzada (C4–C6)' => $avanzadas.' ('.$this->porcentaje($avanzadas, max(1, $clasificadas->count())).')',
                'Úlcera activa (C6)' => (string) ($conteo['C6'] ?? 0),
                'Casos sintomáticos (sufijo s)' => (string) $this->conSufijo('s'),
                'Casos asintomáticos (sufijo a)' => (string) $this->conSufijo('a'),
                'Pacientes distintos' => (string) $consultas->pluck('patient_id')->unique()->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>
     */
    private function claseClinica(Collection $consultas): array
    {
        $conteo = $this->conteoClases();
        $base = max(1, $this->clasificadas()->count());

        $filas = [];

        foreach (self::CLASES as $clase => $descripcion) {
            $cantidad = $conteo[$clase] ?? 0;
            $filas[] = [$clase, $descripcion, (string) $cantidad, $this->porcentaje($cantidad, $base)];
        }

        // Lo que el médico escribió y no encaja en C0–C6 no se descarta: se
        // agrupa al final para que el total de la tabla siga cuadrando con el
        // número de consultas clasificadas.
        $otros = $this->clasificadas()->count() - array_sum($conteo);

        if ($otros > 0) {
            $filas[] = ['Otro', 'Valor fuera de la escala C0–C6', (string) $otros, $this->porcentaje($otros, $base)];
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Clase clínica (C)',
            'encabezados' => ['Clase', 'Descripción', 'Consultas', '%'],
            'filas' => $filas,
            'anchos' => [10, 58, 16, 16],
        ];
    }

    /**
     * Etiología, anatomía y fisiopatología, cada eje con su tabla.
     *
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<int, array<string, mixed>|null>
     */
    private function ejesDelCatalogo(Collection $consultas): array
    {
        $marcadas = ConteoOpciones::porCategoria(
            $consultas->pluck('id')->all(),
            ['ceap_diagnostico']
        )['ceap_diagnostico'] ?? [];

        $secciones = [];

        foreach (self::EJES as $titulo => $valores) {
            $conteo = [];

            foreach ($valores as $valor) {
                if (! empty($marcadas[$valor])) {
                    $conteo[$valor] = $marcadas[$valor];
                }
            }

            $secciones[] = $this->tablaFrecuencia($titulo, 'Categoría', $conteo, $consultas->count());
        }

        return $secciones;
    }

    /**
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @return array<string, mixed>|null
     */
    private function ubicacion(Collection $consultas): ?array
    {
        $nombres = ['MID' => 'Miembro inferior derecho', 'MII' => 'Miembro inferior izquierdo', 'BILATERAL' => 'Bilateral'];

        $conteo = [];

        foreach ($nombres as $clave => $nombre) {
            $cantidad = $consultas->where('ubicacion_patologia', $clave)->count();

            if ($cantidad > 0) {
                $conteo["{$nombre} ({$clave})"] = $cantidad;
            }
        }

        return $this->tablaFrecuencia('Ubicación de la patología', 'Ubicación', $conteo, $consultas->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    /**
     * Consultas con clase clínica anotada.
     *
     * @return Collection<int, ClinicalHistory>
     */
    private function clasificadas(): Collection
    {
        return $this->consultas()->filter(fn (ClinicalHistory $c) => Formato::hayDato($c->ceap_c));
    }

    /**
     * Consultas por clase base, ignorando el sufijo: C2a y C2s son las dos
     * clase 2, y separarlas partiría en dos la fila que interesa contar.
     *
     * @return array<string, int>
     */
    private function conteoClases(): array
    {
        $conteo = array_fill_keys(array_keys(self::CLASES), 0);

        foreach ($this->clasificadas() as $consulta) {
            $clase = $this->claseBase($consulta->ceap_c);

            if ($clase !== null) {
                $conteo[$clase]++;
            }
        }

        return $conteo;
    }

    /** «c2a», « C2 » y «C2s» son todos C2. */
    private function claseBase(?string $valor): ?string
    {
        if (! preg_match('/^\s*C\s*([0-6])\s*[as]?\s*$/i', (string) $valor, $partes)) {
            return null;
        }

        return 'C'.$partes[1];
    }

    /** Cuántas consultas llevan el sufijo pedido: `a` asintomático, `s` sintomático. */
    private function conSufijo(string $sufijo): int
    {
        return $this->clasificadas()
            ->filter(fn (ClinicalHistory $c) => preg_match('/^\s*C\s*[0-6]\s*'.$sufijo.'\s*$/i', (string) $c->ceap_c) === 1)
            ->count();
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
