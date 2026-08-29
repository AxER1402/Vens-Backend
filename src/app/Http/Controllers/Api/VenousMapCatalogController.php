<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MapeoVenoso\Catalogo;
use Illuminate\Http\JsonResponse;

class VenousMapCatalogController extends Controller
{
    /**
     * Entregar el catálogo clínico del mapeo venoso: hallazgos, zonas anatómicas
     * y datos de la plantilla.
     *
     * El editor lo consume para construir su barra de herramientas y su leyenda,
     * y el backend lo usa para validar el documento vectorial y para redactar el
     * reporte. Servirlo desde aquí es lo que evita que el catálogo se bifurque en
     * dos copias que acaben discrepando: si el editor pudiera ofrecer un hallazgo
     * que el backend no conoce, ese hallazgo se guardaría y el informe lo
     * imprimiría como un código sin nombre.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'plantilla' => Catalogo::plantilla(),
                'miembros' => Catalogo::miembros(),
                'zonas' => Catalogo::zonas(),
                'hallazgos' => Catalogo::hallazgos(),
                'grosores' => Catalogo::grosores(),
                'limites' => config('mapeo-venoso.limites'),
                'versiones' => Catalogo::versiones(),
            ],
        ], 200);
    }
}
