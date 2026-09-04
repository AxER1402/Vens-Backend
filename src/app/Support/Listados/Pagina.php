<?php

namespace App\Support\Listados;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Paginación opcional de un listado.
 *
 * Opcional a propósito: las mismas rutas alimentan las tablas de la pantalla
 * —que sí quieren páginas— y los selectores de paciente o los reportes, que
 * necesitan la lista entera para poder contar y buscar. Si paginar fuera
 * obligatorio, cada uno de esos consumidores tendría que aprender a recorrer
 * páginas para hacer algo que hoy hace de una vez.
 *
 * Así que la petición decide: sin `page` ni `per_page` la respuesta es el
 * arreglo de siempre; con cualquiera de los dos viene envuelta con su
 * `meta`. Ninguna pantalla existente cambia de comportamiento sin pedirlo.
 */
class Pagina
{
    /** Lo que cabe en una pantalla sin obligar a desplazarse eternamente. */
    public const POR_DEFECTO = 30;

    /** Tope duro: pedir 5.000 filas «paginadas» es no paginar. */
    public const MAXIMO = 200;

    public static function pedida(Request $request): bool
    {
        return $request->filled('page') || $request->filled('per_page');
    }

    /**
     * Resuelve el listado: paginado si lo pidieron, completo si no.
     *
     * @return array{data: mixed, meta: array<string, int>|null}
     */
    public static function resolver(Request $request, Builder $query): array
    {
        if (! self::pedida($request)) {
            return ['data' => $query->get(), 'meta' => null];
        }

        $porPagina = (int) $request->input('per_page', self::POR_DEFECTO);
        $porPagina = max(1, min($porPagina, self::MAXIMO));

        $pagina = $query->paginate($porPagina)->appends($request->query());

        return [
            'data' => $pagina->items(),
            'meta' => [
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ];
    }

    /**
     * Respuesta JSON con la forma que ya usan todos los listados, más `meta`
     * cuando corresponde.
     *
     * @return array<string, mixed>
     */
    public static function respuesta(Request $request, Builder $query): array
    {
        $resuelto = self::resolver($request, $query);

        $cuerpo = ['success' => true, 'data' => $resuelto['data']];

        if ($resuelto['meta'] !== null) {
            $cuerpo['meta'] = $resuelto['meta'];
        }

        return $cuerpo;
    }
}
