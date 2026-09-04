<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Reportes\Emision;
use App\Support\Reportes\Estadisticos\CatalogoReportes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes de período: los que resumen la actividad de la clínica entre dos
 * fechas.
 *
 * A diferencia de los informes de un expediente —que se piden por el id de su
 * registro y los emite `ReporteController`— estos se piden por clave y rango, y
 * el permiso **sí** depende del reporte: quien puede imprimir el expediente de
 * un paciente no tiene por qué ver la producción del personal ni la
 * epidemiología de la consulta. Por eso el rol se comprueba aquí, contra lo que
 * cada reporte declara en el catálogo, y no con un `role:` fijo en la ruta.
 */
class ReportePeriodoController extends Controller
{
    /**
     * Catálogo de reportes que este usuario puede emitir.
     *
     * La pantalla de reportes se pinta con esto: si un reporte cambia de
     * permisos o se retira, la interfaz se entera sin tocar el frontend.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CatalogoReportes::descriptores(
                $request->user(),
                $request->query('modulo')
            ),
        ]);
    }

    /**
     * Emitir un reporte en PDF o Word.
     *
     * `?desde=&hasta=` acotan el período —el mes en curso si no se mandan— y
     * `?patient_id=&medico_id=` lo recortan cuando el reporte admite ese filtro.
     */
    public function emitir(Request $request, string $clave): StreamedResponse|JsonResponse
    {
        if (! CatalogoReportes::existe($clave)) {
            return response()->json([
                'success' => false,
                'message' => "El reporte '{$clave}' no existe.",
            ], 404);
        }

        if (! in_array($request->user()?->rol, CatalogoReportes::roles($clave), true)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para emitir este reporte.',
            ], 403);
        }

        $formato = Emision::formato($request);

        if ($formato instanceof JsonResponse) {
            return $formato;
        }

        $reporte = CatalogoReportes::construir($clave, $request, $request->user());

        return Emision::descargar($reporte->construir(), $formato);
    }
}
