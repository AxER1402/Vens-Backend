{{--
    Factura electrónica certificada.

    Lleva lo que exige un DTE —emisor, autorización, receptor, detalle con el
    IVA de cada renglón, certificador y QR— con los colores y el logo de la
    clínica, los mismos de los informes.

    Se escribe para mPDF (CSS 2.1): las columnas van con tablas. El QR lo dibuja
    mPDF con <barcode>, con el paquete mpdf/qrcode.
--}}
<style>
    body { font-family: sans-serif; font-size: 8.8pt; color: #1B2A42; line-height: 1.4; }

    /* ── Membrete ───────────────────────────────────────────────────────── */
    .membrete { border-collapse: collapse; width: 100%; }
    .membrete td { vertical-align: middle; padding: 0; }
    .emisor-nombre { font-size: 11pt; font-weight: bold; color: #243757; }
    .emisor-dato { font-size: 8pt; color: #3A5F6F; }
    .regla { border-bottom: 0.8pt solid #0C7D8C; margin: 2.5mm 0 4mm 0; }

    /* ── Autorización y receptor ────────────────────────────────────────── */
    .bloques { border-collapse: separate; border-spacing: 0; width: 100%; margin-bottom: 4mm; }
    .bloque { border: 0.5pt solid #C9D4DA; background-color: #F4F7F8; padding: 2.4mm 3mm; vertical-align: top; }
    .bloque-titulo { font-size: 7.4pt; font-weight: bold; color: #0C7D8C; text-transform: uppercase; letter-spacing: 0.4pt; margin-bottom: 1.4mm; }
    .par { border-collapse: collapse; width: 100%; }
    .par td { padding: 0.5mm 0; vertical-align: top; font-size: 8.4pt; }
    .et { color: #3A5F6F; width: 38%; }
    .va { font-weight: bold; color: #1B2A42; }
    .uuid { font-family: monospace; font-size: 8.6pt; font-weight: bold; color: #243757; }

    /* ── Detalle ────────────────────────────────────────────────────────── */
    .detalle { border-collapse: collapse; width: 100%; }
    .detalle th {
        background-color: #243757; color: #FFFFFF; font-size: 7.6pt; font-weight: bold;
        padding: 1.8mm 1.2mm; text-align: center; vertical-align: middle;
    }
    .detalle td { border-bottom: 0.4pt solid #DDE4E8; padding: 1.8mm 1.2mm; vertical-align: top; font-size: 8.4pt; }
    .detalle tr.par-fila td { background-color: #F8FAFB; }
    .num { text-align: right; }
    .cen { text-align: center; }
    .impuesto { font-size: 7.4pt; color: #3A5F6F; }
    .totales td { border-top: 0.8pt solid #243757; border-bottom: none; font-weight: bold; background-color: #EAF3F4; }

    /* ── Resumen y pie ──────────────────────────────────────────────────── */
    .resumen { border-collapse: collapse; width: 100%; margin-top: 4mm; }
    .resumen td { vertical-align: top; }
    .cuentas { border-collapse: collapse; width: 100%; }
    .cuentas td { padding: 1mm 2mm; font-size: 8.6pt; }
    .cuentas .gran-total td { border-top: 0.8pt solid #0C7D8C; font-size: 10.5pt; font-weight: bold; color: #243757; }
    .en-letras { font-size: 8pt; color: #3A5F6F; font-style: italic; margin-top: 1.5mm; }
    .frase { font-size: 8pt; font-weight: bold; color: #243757; margin-top: 3mm; }

    .certificacion { border-collapse: collapse; width: 100%; margin-top: 6mm; border: 0.5pt solid #C9D4DA; }
    .certificacion td { padding: 2.4mm 3mm; vertical-align: middle; }
</style>

{{-- Membrete: quien emite a la izquierda, qué documento es a la derecha --}}
<table class="membrete">
    <tr>
        @if ($logo)
            <td width="28%"><img src="{{ $logo }}" width="132" alt=""></td>
        @endif
        <td width="{{ $logo ? 44 : 72 }}%" style="padding-left: 2mm;">
            <div class="emisor-nombre">{{ $emisor['nombre'] }}</div>
            @if (! empty($emisor['nombre_comercial']))
                <div class="emisor-dato">{{ $emisor['nombre_comercial'] }}</div>
            @endif
            <div class="emisor-dato">NIT {{ $emisor['nit'] }}</div>
            @if (! empty($emisor['direccion']))
                <div class="emisor-dato">{{ $emisor['direccion'] }}</div>
            @endif
        </td>
        <td width="28%" style="text-align: right;">
            <div style="font-size: 16pt; font-weight: bold; color: #0C7D8C;">FACTURA</div>
            <div style="font-size: 7pt; color: #3A5F6F;">DOCUMENTO TRIBUTARIO ELECTRÓNICO</div>
        </td>
    </tr>
</table>
<div class="regla"></div>

{{-- Autorización y receptor, lado a lado --}}
<table class="bloques">
    <tr>
        <td class="bloque" width="54%">
            <div class="bloque-titulo">Autorización</div>
            <div class="uuid">{{ $factura->fel_uuid }}</div>
            <table class="par" style="margin-top: 1.4mm;">
                <tr><td class="et">Serie</td><td class="va">{{ $factura->fel_serie }}</td></tr>
                <tr><td class="et">Número de DTE</td><td class="va">{{ $factura->fel_numero }}</td></tr>
                <tr><td class="et">Emisión</td><td class="va">{{ $emision }}</td></tr>
                <tr><td class="et">Certificación</td><td class="va">{{ $certificacion }}</td></tr>
                <tr><td class="et">Moneda</td><td class="va">{{ $factura->moneda }}</td></tr>
            </table>
        </td>
        <td width="2%"></td>
        <td class="bloque" width="44%">
            <div class="bloque-titulo">Receptor</div>
            <table class="par">
                <tr><td class="et">NIT</td><td class="va">{{ $factura->nit_receptor }}</td></tr>
                <tr><td class="et">Nombre</td><td class="va">{{ $factura->nombre_receptor }}</td></tr>
                @if ($factura->direccion_receptor)
                    <tr><td class="et">Dirección</td><td class="va">{{ $factura->direccion_receptor }}</td></tr>
                @endif
                @if ($factura->metodo_pago)
                    <tr><td class="et">Pago</td><td class="va">{{ $factura->metodo_pago }}</td></tr>
                @endif
                <tr><td class="et">Control interno</td><td class="va">{{ $factura->correlativo }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="detalle">
    <thead>
        <tr>
            <th width="5%">No.</th>
            <th width="9%">Tipo</th>
            <th width="8%">Cant.</th>
            <th width="30%">Descripción</th>
            <th width="12%">Precio unit. (Q)</th>
            <th width="10%">Descuento (Q)</th>
            <th width="12%">Total (Q)</th>
            <th width="14%">IVA (Q)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($renglones as $r)
            <tr class="{{ $loop->even ? 'par-fila' : '' }}">
                <td class="cen">{{ $r['no'] }}</td>
                <td class="cen">{{ $r['tipo'] }}</td>
                <td class="cen">{{ $r['cantidad'] }}</td>
                <td>{{ $r['descripcion'] }}</td>
                <td class="num">{{ $r['precio'] }}</td>
                <td class="num">{{ $r['descuento'] }}</td>
                <td class="num">{{ $r['total'] }}</td>
                <td class="num impuesto">{{ $r['iva'] }}</td>
            </tr>
        @endforeach
        <tr class="totales">
            <td colspan="5" class="num">Totales</td>
            <td class="num">{{ $totales['descuento'] }}</td>
            <td class="num">{{ $totales['total'] }}</td>
            <td class="num">{{ $totales['iva'] }}</td>
        </tr>
    </tbody>
</table>

<table class="resumen">
    <tr>
        <td width="55%">
            @if ($frase)
                <div class="frase">{{ $frase }}</div>
            @endif
        </td>
        <td width="45%">
            <table class="cuentas">
                <tr><td>Subtotal</td><td class="num">Q {{ $totales['subtotal'] }}</td></tr>
                @if ((float) $factura->descuento > 0)
                    <tr><td>Descuento</td><td class="num">− Q {{ $totales['descuento'] }}</td></tr>
                @endif
                <tr><td>IVA incluido</td><td class="num">Q {{ $totales['iva_resumen'] }}</td></tr>
                <tr class="gran-total"><td>Total</td><td class="num">Q {{ $totales['total'] }}</td></tr>
            </table>
            <div class="en-letras">{{ $totales['en_letras'] }}</div>
        </td>
    </tr>
</table>

<table class="certificacion">
    <tr>
        <td width="80%">
            <div class="bloque-titulo">Datos del certificador</div>
            <div>{{ $certificador['nombre'] }}@if (! empty($certificador['nit'])) &nbsp;·&nbsp; NIT {{ $certificador['nit'] }}@endif</div>
            <div class="emisor-dato" style="margin-top: 1mm;">Autorización {{ $factura->fel_uuid }}</div>
        </td>
        <td width="20%" style="text-align: right;">
            <barcode code="{{ $qr }}" type="QR" size="0.8" error="M" disableborder="1" />
        </td>
    </tr>
</table>
