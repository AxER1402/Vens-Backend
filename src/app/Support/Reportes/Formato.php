<?php

namespace App\Support\Reportes;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Formato de los valores que se imprimen en los reportes.
 *
 * Vive aparte de las plantillas porque el PDF y el Word son dos presentaciones
 * del mismo contenido: si cada renderizador decidiera por su cuenta cómo se ve
 * un campo vacío o cuántos decimales lleva una temperatura, los dos documentos
 * de la misma consulta acabarían diciendo cosas distintas.
 */
class Formato
{
    /**
     * Marca de campo sin dato.
     *
     * Se imprime un guión largo en vez de dejar el hueco en blanco: en un
     * expediente clínico "no se registró" y "se me olvidó imprimirlo" tienen que
     * poder distinguirse a simple vista.
     */
    public const VACIO = '—';

    /**
     * Valor de texto, o la marca de vacío.
     */
    public static function valor(mixed $valor): string
    {
        if ($valor === null || $valor === '' || (is_string($valor) && trim($valor) === '')) {
            return self::VACIO;
        }

        return trim((string) $valor);
    }

    /**
     * ¿Hay algo que imprimir? Lo usan los constructores de datos para omitir
     * secciones enteras: un informe de un paciente hombre no debe llevar un
     * bloque de antecedentes ginecológicos en blanco.
     */
    public static function hayDato(mixed $valor): bool
    {
        if (is_array($valor)) {
            return array_filter($valor, fn ($v) => self::hayDato($v)) !== [];
        }

        return $valor !== null && $valor !== '' && ! (is_string($valor) && trim($valor) === '');
    }

    /**
     * Fecha corta: 28/08/2026.
     */
    public static function fecha(mixed $fecha): string
    {
        $carbon = self::aCarbon($fecha);

        return $carbon?->format('d/m/Y') ?? self::VACIO;
    }

    /**
     * Fecha larga en español: 28 de agosto de 2026. Se usa en el pie de emisión,
     * donde el documento se lee como carta y no como formulario.
     */
    public static function fechaLarga(mixed $fecha): string
    {
        $carbon = self::aCarbon($fecha);

        return $carbon?->locale('es')->isoFormat('D [de] MMMM [de] YYYY') ?? self::VACIO;
    }

    /**
     * Fecha y hora: 28/08/2026 15:40.
     */
    public static function fechaHora(mixed $fecha): string
    {
        $carbon = self::aCarbon($fecha);

        return $carbon?->format('d/m/Y H:i') ?? self::VACIO;
    }

    /**
     * Número con unidad, sin ceros de relleno: 36.5 °C, 12 cm, 0.5 %.
     *
     * Los decimales que la base guarda por la definición de la columna
     * (`peso decimal(5,2)` devuelve "68.00") se recortan: en un informe clínico
     * el peso se lee "68 kg", no "68.00 kg".
     */
    public static function numero(mixed $valor, string $unidad = ''): string
    {
        if (! is_numeric($valor)) {
            return self::VACIO;
        }

        $texto = rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');

        return $unidad === '' ? $texto : "{$texto} {$unidad}";
    }

    /**
     * Entero, o la marca de vacío. El cero sí se imprime: "0 abortos" es un dato
     * clínico, no un campo sin llenar.
     */
    public static function entero(mixed $valor): string
    {
        return is_numeric($valor) ? (string) (int) $valor : self::VACIO;
    }

    /**
     * Sí / No / sin dato. Un booleano nulo no es "No": significa que no se
     * preguntó.
     */
    public static function booleano(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return self::VACIO;
        }

        return $valor ? 'Sí' : 'No';
    }

    /**
     * Lista de selecciones separada por comas.
     *
     * @param  array<int, string>|null  $valores
     */
    public static function lista(?array $valores, string $separador = ', '): string
    {
        if (! $valores) {
            return self::VACIO;
        }

        $limpios = array_filter(array_map('trim', $valores), fn ($v) => $v !== '');

        return $limpios === [] ? self::VACIO : implode($separador, $limpios);
    }

    /**
     * Presión arterial: la base la guarda compuesta ('120/80').
     */
    public static function presion(mixed $valor): string
    {
        return self::hayDato($valor) ? trim((string) $valor).' mmHg' : self::VACIO;
    }

    /**
     * Nombre de archivo del documento descargado:
     * historia-clinica_maria-portillo_2026-08-28.pdf
     */
    public static function nombreArchivo(string $prefijo, ?string $paciente, mixed $fecha, string $extension): string
    {
        $carbon = self::aCarbon($fecha);

        $partes = array_filter([
            $prefijo,
            $paciente ? \Illuminate\Support\Str::slug($paciente) : null,
            $carbon?->format('Y-m-d'),
        ]);

        return implode('_', $partes).'.'.$extension;
    }

    /**
     * Convertir a Carbon lo que venga del modelo (Carbon, string o null).
     */
    private static function aCarbon(mixed $fecha): ?CarbonInterface
    {
        if ($fecha instanceof CarbonInterface) {
            return $fecha;
        }

        if (! self::hayDato($fecha)) {
            return null;
        }

        try {
            return Carbon::parse($fecha);
        } catch (\Throwable) {
            // Una fecha ilegible no debe impedir que se emita el informe entero
            return null;
        }
    }
}
