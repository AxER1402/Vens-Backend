<?php

namespace App\Support\Reportes;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renderiza a PDF el documento que arman los constructores de datos.
 *
 * Se eligió mPDF y no dompdf porque una historia clínica sale en dos o tres
 * páginas y necesita el membrete repetido en cada una más "Página X de Y":
 * mPDF lo resuelve de forma nativa con SetHTMLHeader/SetHTMLFooter, mientras que
 * en dompdf hay que simularlo con posiciones fijas y el control de los cortes de
 * tabla es peor. La marca de agua de los borradores también viene incluida.
 */
class GeneradorPdf
{
    /**
     * Componer el PDF y devolver sus bytes.
     *
     * @param  array<string, mixed>  $doc
     */
    public function generar(array $doc): string
    {
        $mpdf = $this->motor($doc);

        $centro = config('reportes.centro');
        $logo = $this->rutaLogo();

        $mpdf->SetHTMLHeader(view('reportes.partes.encabezado', [
            'centro' => $centro,
            'logo' => $logo,
        ])->render());

        $mpdf->SetHTMLFooter(view('reportes.partes.pie', [
            'centro' => $centro,
            'titulo' => $doc['titulo'],
        ])->render());

        // Un borrador se puede imprimir, pero no debe poder confundirse con un
        // informe firmado. Un documento puede además traer su propia leyenda
        // —«ANULADO», «SIN CERTIFICAR»—: la palabra «BORRADOR» sirve para una
        // consulta sin cerrar y no para una factura que sí se emitió.
        $marcaAgua = $doc['marca_agua']
            ?? (! empty($doc['borrador']) ? config('reportes.borrador.texto') : null);

        if ($marcaAgua) {
            $mpdf->SetWatermarkText($marcaAgua);
            $mpdf->watermarkTextAlpha = config('reportes.borrador.opacidad');
            $mpdf->showWatermarkText = true;
        }

        $mpdf->SetTitle($doc['titulo']);
        $mpdf->SetAuthor($centro['nombre']);
        $mpdf->SetCreator($centro['nombre']);

        $mpdf->WriteHTML(view('reportes.documento', ['doc' => $doc])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Instancia de mPDF con el tamaño y los márgenes del informe.
     *
     * @param  array<string, mixed>  $doc
     */
    private function motor(array $doc): Mpdf
    {
        $pagina = config('reportes.pagina');

        // El mapeo venoso va apaisado: su plantilla es claramente horizontal
        // (1450 × 848) y en vertical la lámina quedaría ilegible.
        $apaisado = ($doc['orientacion'] ?? null) === 'apaisada';

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => $pagina['formato'].($apaisado ? '-L' : ''),
            'orientation' => $apaisado ? 'L' : 'P',
            'margin_left' => $pagina['margen_izquierdo'],
            'margin_right' => $pagina['margen_derecho'],
            'margin_top' => $pagina['margen_superior'],
            'margin_bottom' => $pagina['margen_inferior'],
            'margin_header' => $pagina['margen_encabezado'],
            'margin_footer' => $pagina['margen_pie'],
            // El contenedor corre como www-data: el temporal va dentro de
            // storage/, que ya es escribible, y no en el /tmp de la imagen.
            'tempDir' => $this->directorioTemporal(),
        ]);
    }

    /**
     * Ruta absoluta del logo, o null si no está en el disco.
     *
     * Un membrete sin logo es aceptable; que el informe falle porque falta un
     * PNG decorativo, no.
     */
    private function rutaLogo(): ?string
    {
        $relativa = config('reportes.centro.logo');

        if (! $relativa) {
            return null;
        }

        $ruta = public_path($relativa);

        return is_file($ruta) ? $ruta : null;
    }

    private function directorioTemporal(): string
    {
        $dir = config('reportes.temp_dir');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
