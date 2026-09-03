<?php

namespace App\Support\Reportes\Estadisticos;

use Illuminate\Support\Facades\DB;

/**
 * Cuántas veces se marcó cada opción del catálogo clínico en un conjunto de
 * consultas.
 *
 * Las nueve listas de la historia clínica (síntomas, enfermedades, CEAP,
 * indicaciones…) viven todas en la misma tabla pivote, así que tres reportes
 * distintos necesitan exactamente esta cuenta. Se resuelve en SQL y no cargando
 * las relaciones: contar en PHP obligaría a traer la historia entera de cada
 * consulta del período para acabar quedándose con un número.
 */
final class ConteoOpciones
{
    /**
     * Las claves de las consultas viajan en un `IN (…)`, y SQLite —el motor con
     * el que corren las pruebas— admite 999 parámetros por sentencia. Se parte
     * en bloques por debajo de ese tope para que un reporte anual no reviente
     * la consulta.
     */
    private const TAMANO_BLOQUE = 500;

    /**
     * Conteo por categoría y valor, respetando el orden del catálogo.
     *
     * @param  array<int, int>  $consultas  Ids de las historias clínicas
     * @param  array<int, string>  $categorias
     * @return array<string, array<string, int>> categoría => [valor => veces marcado]
     */
    public static function porCategoria(array $consultas, array $categorias): array
    {
        if ($consultas === [] || $categorias === []) {
            return [];
        }

        $conteo = [];

        foreach (array_chunk($consultas, self::TAMANO_BLOQUE) as $bloque) {
            $filas = DB::table('clinical_history_option as marcadas')
                ->join('clinical_options as opciones', 'opciones.id', '=', 'marcadas.clinical_option_id')
                ->whereIn('marcadas.clinical_history_id', $bloque)
                ->whereIn('opciones.categoria', $categorias)
                ->groupBy('opciones.categoria', 'opciones.valor', 'opciones.orden')
                ->orderBy('opciones.categoria')
                ->orderBy('opciones.orden')
                ->get([
                    'opciones.categoria as categoria',
                    'opciones.valor as valor',
                    DB::raw('COUNT(*) as total'),
                ]);

            foreach ($filas as $fila) {
                $actual = $conteo[$fila->categoria][$fila->valor] ?? 0;
                $conteo[$fila->categoria][$fila->valor] = $actual + (int) $fila->total;
            }
        }

        return $conteo;
    }
}
