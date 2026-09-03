<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\User;
use App\Support\Reportes\Ficha;
use App\Support\Reportes\Formato;

/**
 * Base de los reportes de período: los que resumen la actividad de la clínica
 * entre dos fechas, frente a los informes de un expediente concreto.
 *
 * La diferencia con `DatosHistoriaClinica` y compañía no está en cómo se pinta
 * el documento —es el mismo: ficha, secciones tipadas y firma— sino en qué
 * ocupa la ficha. Un informe de consulta la encabeza con el paciente; uno de
 * período, con el rango y los filtros aplicados, porque sin eso una tabla de
 * «42 consultas» no dice de cuándo ni de quién.
 *
 * Cada reporte concreto solo tiene que decir cómo se titula y qué secciones
 * lleva; el resto (envoltura, ficha, firma, nombre del archivo) se resuelve
 * aquí una vez para los diez.
 */
abstract class ReportePeriodo
{
    /**
     * Tope de filas de los listados de detalle.
     *
     * Un reporte de un año puede tener miles de registros y nadie imprime eso:
     * pasado el tope el listado se corta y se dice cuántos quedaron fuera, que
     * es más honesto que emitir un PDF de 300 páginas o que recortar en
     * silencio.
     */
    protected const MAX_FILAS = 300;

    /**
     * @param  array<string, mixed>  $filtros  Filtros ya validados (patient_id, medico_id…)
     */
    public function __construct(
        protected readonly Periodo $periodo,
        protected readonly array $filtros = [],
        protected readonly ?User $emisor = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Lo que define cada reporte
    |--------------------------------------------------------------------------
    */

    /** Título que encabeza el documento. */
    abstract public function titulo(): string;

    /** Prefijo del archivo descargado: `citas_2026-09-30.pdf`. */
    abstract public function archivo(): string;

    /**
     * Cuerpo del reporte.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function secciones(): array;

    /** Línea bajo el título. Se sobrescribe cuando aporta algo. */
    public function subtitulo(): string
    {
        return 'Período '.$this->periodo->etiqueta();
    }

    /**
     * Datos que acompañan al período en la ficha de cabecera. Cada reporte
     * añade los suyos (total de registros, filtros propios…).
     *
     * @return array<string, string>
     */
    protected function metaExtra(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Documento
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    public function construir(): array
    {
        return [
            'titulo' => $this->titulo(),
            'subtitulo' => $this->subtitulo(),
            'archivo' => $this->archivo(),
            'borrador' => false,
            // La ficha de un reporte de período no lleva paciente: lo que hay
            // que poder leer de un vistazo es el rango y los filtros.
            'paciente' => [],
            'meta' => array_merge([
                'Período' => $this->periodo->etiqueta(),
                'Días' => (string) $this->periodo->dias(),
            ], $this->metaExtra()),
            'secciones' => $this->secciones(),
            'firma' => Ficha::firma($this->emisor),
            'nombre_archivo_base' => null,
            'fecha_archivo' => $this->periodo->fechaArchivo(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Piezas comunes
    |--------------------------------------------------------------------------
    */

    /**
     * Aviso de que el período no tiene registros.
     *
     * Se dice explícitamente en vez de emitir un documento con tablas vacías:
     * un reporte en blanco no distingue «no hubo actividad» de «el reporte
     * falló».
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sinDatos(string $mensaje): array
    {
        return [[
            'tipo' => 'texto',
            'titulo' => 'Sin registros en el período',
            'texto' => $mensaje,
        ]];
    }

    /**
     * Tabla de «concepto / cantidad / porcentaje», que es la forma de la mitad
     * de las secciones de estos reportes.
     *
     * @param  array<string, int>  $conteo  concepto => cantidad, ya ordenado
     * @return array<string, mixed>|null
     */
    protected function tablaFrecuencia(
        ?string $titulo,
        string $encabezado,
        array $conteo,
        int $base,
        string $etiquetaCantidad = 'Consultas',
    ): ?array {
        if ($conteo === [] || $base <= 0) {
            return null;
        }

        $filas = [];

        foreach ($conteo as $concepto => $cantidad) {
            $filas[] = [
                Formato::valor($concepto),
                (string) $cantidad,
                $this->porcentaje($cantidad, $base),
            ];
        }

        return [
            'tipo' => 'tabla',
            'titulo' => $titulo,
            'encabezados' => [$encabezado, $etiquetaCantidad, '%'],
            'filas' => $filas,
            'anchos' => [58, 21, 21],
        ];
    }

    /**
     * Porcentaje con un decimal. Se calcula aquí y no en la plantilla para que
     * el PDF y el Word no puedan redondear distinto.
     */
    protected function porcentaje(int|float $parte, int|float $total): string
    {
        if ($total <= 0) {
            return Formato::VACIO;
        }

        return number_format($parte * 100 / $total, 1, '.', '').' %';
    }

    /**
     * Promedio con la precisión que haga falta para que no se lea como cero.
     *
     * «16 citas en 365 días» da 0.04, y con un decimal se imprime «0.0», que en
     * un resumen se lee como «no hubo actividad» justo debajo de la línea que
     * dice que hubo dieciséis. Por debajo de la unidad se pasa a dos decimales.
     */
    protected function media(int|float $parte, int|float $total): string
    {
        if ($total <= 0) {
            return Formato::VACIO;
        }

        $valor = $parte / $total;

        return number_format($valor, $valor > 0 && $valor < 1 ? 2 : 1, '.', '');
    }

    /**
     * Recortar un listado al tope imprimible.
     *
     * @param  array<int, array<int, string>>  $filas
     * @return array{0: array<int, array<int, string>>, 1: int} filas y cuántas quedaron fuera
     */
    protected function recortar(array $filas): array
    {
        $sobran = max(0, count($filas) - self::MAX_FILAS);

        return [array_slice($filas, 0, self::MAX_FILAS), $sobran];
    }

    /**
     * Nota al pie de un listado recortado.
     *
     * @return array<string, mixed>|null
     */
    protected function avisoRecorte(int $sobran): ?array
    {
        if ($sobran <= 0) {
            return null;
        }

        return [
            'tipo' => 'texto',
            'titulo' => null,
            'texto' => 'Se listan los primeros '.self::MAX_FILAS." registros; quedan {$sobran} fuera del detalle. "
                .'Los totales y porcentajes de este reporte sí incluyen todos los registros del período. '
                .'Acote el rango de fechas para verlos completos.',
        ];
    }

    /**
     * Ordenar un conteo de mayor a menor y, a igualdad, alfabéticamente: sin el
     * segundo criterio dos ejecuciones del mismo reporte pueden listar los
     * empates en distinto orden y parecer que los datos cambiaron.
     *
     * @param  array<string, int>  $conteo
     * @return array<string, int>
     */
    protected function ordenarPorFrecuencia(array $conteo): array
    {
        uksort($conteo, function (string $a, string $b) use ($conteo) {
            return [$conteo[$b], $a] <=> [$conteo[$a], $b];
        });

        return $conteo;
    }

    /**
     * Texto largo recortado para que quepa en una celda del listado.
     */
    protected function resumir(?string $texto, int $limite = 90): string
    {
        if (! Formato::hayDato($texto)) {
            return Formato::VACIO;
        }

        $limpio = trim(preg_replace('/\s+/', ' ', (string) $texto));

        return mb_strlen($limpio) <= $limite
            ? $limpio
            : mb_substr($limpio, 0, $limite - 1).'…';
    }
}
