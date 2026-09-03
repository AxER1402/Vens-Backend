<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalHistory;
use App\Models\DopplerReport;
use App\Support\Reportes\DatosDoppler;
use App\Support\Reportes\DatosInforme;
use App\Support\Reportes\DatosMapeoVenoso;
use App\Support\Reportes\Emision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Emisión de los informes clínicos imprimibles.
 *
 * Cada endpoint entrega **un registro**: la consulta que se pide, no el
 * historial del paciente.
 *
 * Van sin el middleware `role:` a propósito: quien tiene permiso para leer un
 * expediente lo tiene para imprimirlo, y añadir aquí una restricción que la
 * lectura no tiene solo conseguiría que el personal viera los datos en pantalla
 * pero no pudiera entregárselos al paciente.
 */
class ReporteController extends Controller
{
    /**
     * Informe de una consulta, en PDF o Word.
     *
     * `?partes=historia,mapeo,doppler` elige qué lleva el documento. Se emite
     * uno solo con lo seleccionado en lugar de tres descargas sueltas: lo que el
     * médico entrega es un paquete, y juntarlo a mano después es trabajo que no
     * tiene por qué hacer.
     *
     * Sin el parámetro se incluye todo lo que la consulta tenga.
     */
    public function historiaClinica(Request $request, ClinicalHistory $clinicalHistory): StreamedResponse|JsonResponse
    {
        $formato = $this->formato($request);

        if ($formato instanceof JsonResponse) {
            return $formato;
        }

        $informe = new DatosInforme($clinicalHistory);
        $doc = $informe->construir($informe->resolverPartes($this->partes($request)));

        return $this->descargar($doc, $formato);
    }

    /**
     * Mapeo venoso de una consulta. Solo PDF.
     *
     * Es una lámina a escala con su leyenda: en Word la imagen se convierte en
     * un objeto flotante que se desplaza al primer retoque y pierde la
     * proporción con la que debe imprimirse. Y no hay nada que redactar en él:
     * si el mapeo cambia, se cambia en el editor y se vuelve a emitir.
     */
    public function mapeoVenoso(ClinicalHistory $clinicalHistory): StreamedResponse|JsonResponse
    {
        $mapeo = new DatosMapeoVenoso($clinicalHistory);

        if (! $mapeo->tieneMapeo()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta consulta no tiene un mapeo venoso registrado.',
            ], 404);
        }

        return $this->descargar($mapeo->construir(), 'pdf');
    }

    /**
     * Reporte de Ecodöppler venoso, en PDF o Word.
     */
    public function doppler(Request $request, DopplerReport $dopplerReport): StreamedResponse|JsonResponse
    {
        $formato = $this->formato($request);

        if ($formato instanceof JsonResponse) {
            return $formato;
        }

        $doc = (new DatosDoppler($dopplerReport))->construir();

        return $this->descargar($doc, $formato);
    }

    /*
    |--------------------------------------------------------------------------
    | Emisión
    |--------------------------------------------------------------------------
    |
    | Renderizar y bautizar el archivo es idéntico para estos informes y para
    | los reportes de período, así que vive en App\Support\Reportes\Emision y
    | aquí solo se delega.
    |
    */

    /**
     * @param  array<string, mixed>  $doc
     */
    private function descargar(array $doc, string $formato): StreamedResponse
    {
        return Emision::descargar($doc, $formato);
    }

    /**
     * Partes pedidas, o null si no se especificaron.
     *
     * Se acepta tanto `partes=historia,mapeo` como `partes[]=historia`, que es
     * como lo manda axios cuando recibe un arreglo.
     *
     * @return array<int, string>|null
     */
    private function partes(Request $request): ?array
    {
        if (! $request->has('partes')) {
            return null;
        }

        $partes = $request->input('partes');

        if (is_string($partes)) {
            $partes = explode(',', $partes);
        }

        if (! is_array($partes)) {
            return null;
        }

        return array_values(array_filter(array_map('trim', $partes)));
    }

    /**
     * Formato pedido, o un 422 si no es uno de los que se emiten.
     */
    private function formato(Request $request): string|JsonResponse
    {
        return Emision::formato($request);
    }
}
