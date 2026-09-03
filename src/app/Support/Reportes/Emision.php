<?php

namespace App\Support\Reportes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Puesta en la respuesta HTTP de un documento ya compuesto.
 *
 * Vive aparte de los controladores porque los informes de un expediente y los
 * reportes de período comparten exactamente esto —elegir formato, renderizar y
 * bautizar el archivo— y lo único que cambia entre unos y otros es de dónde
 * salen los datos. Duplicarlo garantizaba que un día el PDF de un módulo se
 * descargara con un nombre y el del otro con otro.
 */
class Emision
{
    /** Formatos que el sistema sabe emitir, con su tipo MIME. */
    public const TIPOS = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Formato pedido, o un 422 si no es uno de los que se emiten.
     */
    public static function formato(Request $request): string|JsonResponse
    {
        $formato = strtolower((string) $request->query('formato', 'pdf'));

        if (! array_key_exists($formato, self::TIPOS)) {
            return response()->json([
                'success' => false,
                'message' => "El formato '{$formato}' no está disponible. Use 'pdf' o 'docx'.",
            ], 422);
        }

        return $formato;
    }

    /**
     * Renderizar el documento y entregarlo como descarga.
     *
     * El documento se compone entero en memoria antes de responder: un informe
     * de una consulta pesa unos cientos de KB y así un fallo del generador sale
     * como error HTTP y no como una descarga truncada.
     *
     * @param  array<string, mixed>  $doc
     */
    public static function descargar(array $doc, string $formato): StreamedResponse
    {
        $contenido = $formato === 'docx'
            ? (new GeneradorWord)->generar($doc)
            : (new GeneradorPdf)->generar($doc);

        $nombre = Formato::nombreArchivo(
            $doc['archivo'],
            $doc['nombre_archivo_base'] ?? null,
            $doc['fecha_archivo'] ?? null,
            $formato
        );

        return response()->streamDownload(
            fn () => print ($contenido),
            $nombre,
            [
                'Content-Type' => self::TIPOS[$formato],
                'Content-Length' => (string) strlen($contenido),
            ]
        );
    }
}
