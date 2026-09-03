<?php

namespace App\Support\Reportes\Estadisticos;

use App\Support\Reportes\Formato;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rango de fechas sobre el que se calcula un reporte de período.
 *
 * Se resuelve una sola vez y viaja a todos los reportes en lugar de que cada
 * uno lea `desde` y `hasta` de la petición: si uno interpretara `hasta` como
 * exclusivo y otro como inclusivo, dos reportes del mismo mes darían totales
 * distintos y no habría forma de saber cuál miente.
 *
 * Ambos extremos son **inclusivos**: quien escribe «del 1 al 30 de septiembre»
 * espera que el 30 cuente.
 */
final class Periodo
{
    private function __construct(
        public readonly Carbon $desde,
        public readonly Carbon $hasta,
    ) {}

    /**
     * Período pedido en la petición.
     *
     * Sin fechas se toma el mes en curso, que es el reporte que se pide nueve
     * de cada diez veces. Si llegan invertidas se enderezan en vez de devolver
     * un reporte vacío: el error está en el formulario, no en los datos.
     */
    public static function desdePeticion(Request $request): self
    {
        $desde = self::fecha($request->query('desde')) ?? Carbon::now()->startOfMonth();
        $hasta = self::fecha($request->query('hasta')) ?? Carbon::now()->endOfMonth();

        return $desde->greaterThan($hasta)
            ? new self($hasta->startOfDay(), $desde->startOfDay())
            : new self($desde->startOfDay(), $hasta->startOfDay());
    }

    /** Período explícito, para pruebas y para componer reportes desde código. */
    public static function entre(string $desde, string $hasta): self
    {
        return new self(Carbon::parse($desde)->startOfDay(), Carbon::parse($hasta)->startOfDay());
    }

    /*
    |--------------------------------------------------------------------------
    | Límites para las consultas
    |--------------------------------------------------------------------------
    */

    /** Extremos como fecha (`date`), para columnas `fecha_consulta` o `fecha_estudio`. */
    public function fechaInicio(): string
    {
        return $this->desde->toDateString();
    }

    public function fechaFin(): string
    {
        return $this->hasta->toDateString();
    }

    /**
     * Extremos como marca de tiempo, para columnas `datetime` como
     * `fecha_hora_inicio`. El final se estira hasta las 23:59:59 porque un
     * `whereBetween` contra la fecha pelada dejaría fuera todas las citas del
     * último día del período.
     *
     * @return array{0: string, 1: string}
     */
    public function limitesHora(): array
    {
        return [
            $this->desde->copy()->startOfDay()->toDateTimeString(),
            $this->hasta->copy()->endOfDay()->toDateTimeString(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Presentación
    |--------------------------------------------------------------------------
    */

    /** Días naturales que abarca, contando los dos extremos. */
    public function dias(): int
    {
        return $this->desde->diffInDays($this->hasta) + 1;
    }

    /** «01/09/2026 — 30/09/2026», o la fecha sola si el período es de un día. */
    public function etiqueta(): string
    {
        if ($this->desde->isSameDay($this->hasta)) {
            return Formato::fecha($this->desde);
        }

        return Formato::fecha($this->desde).' — '.Formato::fecha($this->hasta);
    }

    /**
     * Fecha con la que se bautiza el archivo descargado: el cierre del período,
     * que es lo que identifica al reporte («citas al 30/09»).
     */
    public function fechaArchivo(): Carbon
    {
        return $this->hasta;
    }

    /**
     * Convertir a Carbon lo que llegue por la petición, o null si no es una
     * fecha. Una fecha ilegible se ignora y se cae al período por defecto: es
     * preferible a un 500 en mitad de una descarga.
     */
    private static function fecha(mixed $valor): ?Carbon
    {
        if (! Formato::hayDato($valor)) {
            return null;
        }

        try {
            return Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
