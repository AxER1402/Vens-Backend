<?php

namespace App\Support\Facturacion;

use App\Models\Invoice;
use App\Support\Reportes\GeneradorPdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * La factura electrónica certificada, impresa.
 *
 * Lleva los datos que exige un DTE —emisor, autorización con serie y número,
 * receptor, detalle con el IVA de cada renglón, certificador y QR— con el
 * aspecto de la clínica. No usa la plantilla de los informes porque la
 * cabecera de una factura es otra: la autorización ocupa el lugar de la ficha.
 */
class GeneradorFactura
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public function generar(Invoice $factura): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'Letter',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 18,
            'margin_footer' => 8,
            'tempDir' => GeneradorPdf::directorioTemporal(),
        ]);

        if ($factura->estado === 'Anulada') {
            $mpdf->SetWatermarkText('ANULADA');
            $mpdf->watermarkTextAlpha = 0.08;
            $mpdf->showWatermarkText = true;
        }

        $mpdf->SetTitle("Factura {$factura->fel_serie}-{$factura->fel_numero}");
        $mpdf->SetAuthor((string) config('facturacion.emisor.nombre'));
        $mpdf->SetHTMLFooter(
            '<div style="border-top: 0.4pt solid #C9D4DA; padding-top: 1mm; font-size: 7.2pt; color: #3A5F6F;">'
            .'<table width="100%"><tr><td>'.e((string) config('facturacion.emisor.nombre')).'</td>'
            .'<td style="text-align: right;">Página {PAGENO} de {nbpg}</td></tr></table></div>'
        );

        $mpdf->WriteHTML(view('facturacion.factura', $this->datos($factura))->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(Invoice $factura): array
    {
        $iva = (float) $factura->iva_porcentaje;
        $renglones = [];
        $ivaTotal = 0.0;

        foreach ($factura->items as $i => $item) {
            // El IVA va incluido en el total del renglón y la FEL lo desglosa
            // con seis decimales: total × tasa / (1 + tasa).
            $impuesto = round((float) $item->total * $iva / (100 + $iva), 6);
            $ivaTotal += $impuesto;

            $renglones[] = [
                'no' => $i + 1,
                'tipo' => $item->tipo === 'B' ? 'Bien' : 'Servicio',
                'cantidad' => self::cantidad($item->cantidad),
                'descripcion' => $item->descripcion,
                'precio' => self::monto($item->precio_unitario),
                'descuento' => self::monto($item->descuento),
                'total' => self::monto($item->total),
                'iva' => number_format($impuesto, 6, '.', ''),
            ];
        }

        $emisor = config('facturacion.emisor');
        $certificador = config('facturacion.certificador');

        // La hora sale del momento en que se guardó: fecha_emision es solo
        // la fecha de calendario.
        $emitida = Carbon::parse($factura->fecha_emision->format('Y-m-d').' '.$factura->created_at->format('H:i:s'));

        return [
            'emisor' => $emisor,
            'factura' => $factura,
            'emision' => self::fechaHora($emitida),
            'certificacion' => self::fechaHora($factura->fel_certificado_at),
            'renglones' => $renglones,
            'totales' => [
                'subtotal' => self::monto($factura->subtotal),
                'descuento' => self::monto($factura->descuento),
                'total' => self::monto($factura->total),
                'iva' => number_format($ivaTotal, 6, '.', ''),
                'iva_resumen' => self::monto($ivaTotal),
                'en_letras' => Cantidad::enLetras((float) $factura->total),
            ],
            'frase' => config('facturacion.frase'),
            'certificador' => $certificador,
            'qr' => $this->contenidoQr($factura, (string) ($emisor['nit'] ?? '')),
            'logo' => $this->rutaLogo(),
        ];
    }

    private function rutaLogo(): ?string
    {
        $relativa = config('reportes.centro.logo');
        $ruta = $relativa ? public_path($relativa) : null;

        return $ruta && is_file($ruta) ? $ruta : null;
    }

    private function contenidoQr(Invoice $factura, string $nitEmisor): string
    {
        return strtr((string) config('facturacion.qr'), [
            '{uuid}' => (string) $factura->fel_uuid,
            '{emisor}' => rawurlencode($nitEmisor),
            '{receptor}' => rawurlencode((string) $factura->nit_receptor),
            '{monto}' => number_format((float) $factura->total, 2, '.', ''),
        ]);
    }

    /** «18-sep-2026 11:03:12». */
    private static function fechaHora(?CarbonInterface $momento): string
    {
        if ($momento === null) {
            return '';
        }

        return $momento->format('d').'-'.self::MESES[$momento->month - 1].'-'.$momento->format('Y H:i:s');
    }

    private static function monto(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', ',');
    }

    private static function cantidad(mixed $valor): string
    {
        $numero = (float) $valor;

        return floor($numero) === $numero ? (string) (int) $numero : number_format($numero, 2, '.', ',');
    }
}
