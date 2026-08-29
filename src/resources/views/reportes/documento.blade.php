{{--
    Cuerpo de los informes clínicos imprimibles.

    Una sola plantilla sirve a los tres reportes porque todos reciben la misma
    estructura resuelta (ver App\Support\Reportes): ficha del paciente, una lista
    de secciones tipadas y un pie de firma. Lo que cambia entre un Ecodöppler y
    un mapeo venoso es el contenido, no la forma de presentarlo.

    Tipos de sección: campos | tabla | texto | imagen.

    Se escribe para mPDF, que soporta CSS 2.1: nada de flexbox ni grid, y la
    maquetación de dos columnas va con tablas.
--}}
<style>
    body {
        font-family: sans-serif;
        font-size: 9.2pt;
        color: #1B2A42;
        line-height: 1.45;
    }

    /* ── Membrete y pie ─────────────────────────────────────────────────── */
    .membrete { border-collapse: collapse; }
    .membrete td { vertical-align: middle; padding: 0; }
    .membrete-logo { padding-right: 3mm; }
    .membrete-datos { padding-left: 1mm; }
    .membrete-nombre { font-size: 10.5pt; font-weight: bold; color: #243757; line-height: 1.2; }
    .membrete-especialidad { font-size: 8pt; color: #3A5F6F; }
    .membrete-contacto { text-align: right; font-size: 7.4pt; color: #3A5F6F; line-height: 1.35; }
    .membrete-regla { border-bottom: 0.6pt solid #0C7D8C; margin-top: 1.5mm; }

    .pie { border-top: 0.4pt solid #C9D4DA; padding-top: 1mm; font-size: 7.2pt; color: #3A5F6F; }
    .pie table { border-collapse: collapse; }
    .pie-der { text-align: right; }

    /* ── Título del documento ───────────────────────────────────────────── */
    .titulo {
        font-size: 13pt;
        font-weight: bold;
        color: #243757;
        text-align: center;
        margin: 0 0 0.5mm 0;
    }
    .subtitulo { font-size: 8.6pt; color: #3A5F6F; text-align: center; margin: 0 0 3mm 0; }

    /* ── Ficha del paciente ─────────────────────────────────────────────── */
    .ficha {
        border: 0.5pt solid #C9D4DA;
        background-color: #F4F7F8;
        border-collapse: collapse;
        margin-bottom: 4mm;
    }
    .ficha td { padding: 1.6mm 2.4mm; vertical-align: top; }
    .ficha .et { font-size: 7.2pt; color: #3A5F6F; text-transform: uppercase; letter-spacing: 0.3pt; }
    .ficha .va { font-size: 9.4pt; font-weight: bold; color: #1B2A42; }

    /* ── Secciones ──────────────────────────────────────────────────────── */
    .seccion { margin-bottom: 3.6mm; }

    /*
       Una sección de campos se mantiene entera en la misma página: partirla deja
       una fila huérfana al principio de la siguiente, que se lee como si el dato
       perteneciera a otro epígrafe. Las tablas sí pueden partirse —una lista de
       hallazgos puede ser larga— pero nunca por la mitad de una fila, y mPDF
       repite el <thead> en cada página.
    */
    .seccion-campos { page-break-inside: avoid; }
    .seccion-titulo {
        font-size: 9pt;
        font-weight: bold;
        color: #0C7D8C;
        text-transform: uppercase;
        letter-spacing: 0.4pt;
        border-bottom: 0.5pt solid #0C7D8C;
        padding-bottom: 0.8mm;
        margin-bottom: 1.8mm;
        page-break-after: avoid;
    }

    /* Pares etiqueta/valor a dos columnas */
    .campos { width: 100%; border-collapse: collapse; }
    .campos td { padding: 0.9mm 2mm 0.9mm 0; vertical-align: top; }
    .campos .et { width: 21%; font-size: 7.8pt; color: #3A5F6F; }
    .campos .va { width: 29%; font-size: 9pt; }

    /* Tablas de datos */
    .datos { width: 100%; border-collapse: collapse; margin-top: 1mm; }
    .datos th {
        background-color: #243757;
        color: #FFFFFF;
        font-size: 7.6pt;
        font-weight: bold;
        text-align: left;
        padding: 1.3mm 1.8mm;
    }
    .datos tr { page-break-inside: avoid; }
    .datos td { border-bottom: 0.4pt solid #DCE4E8; padding: 1.2mm 1.8mm; font-size: 8.4pt; }
    .datos tr.par td { background-color: #F7FAFB; }

    /* Portada de anexo */
    .anexo-portada {
        font-size: 11pt;
        font-weight: bold;
        color: #243757;
        background-color: #EDF3F5;
        border-left: 2.5pt solid #0C7D8C;
        padding: 2.2mm 3mm;
        margin-bottom: 3mm;
        page-break-after: avoid;
    }

    /* Texto libre */
    .texto { font-size: 9pt; text-align: justify; white-space: pre-line; }

    /* Lámina del mapeo venoso */
    .lamina { text-align: center; margin: 1mm 0 2mm 0; page-break-inside: avoid; }
    .lamina img { width: 100%; }
    .lamina-pie { font-size: 7.4pt; color: #3A5F6F; text-align: center; margin-bottom: 2mm; }

    /* ── Firma ──────────────────────────────────────────────────────────── */
    /* La firma nunca se parte y no se queda sola: si no cabe entera se va con
       el último bloque a la página siguiente. */
    .firma { margin-top: 8mm; page-break-inside: avoid; }
    .firma-linea { border-top: 0.5pt solid #1B2A42; width: 62mm; margin-bottom: 1mm; }
    .firma-nombre { font-size: 9pt; font-weight: bold; }
    .firma-detalle { font-size: 7.6pt; color: #3A5F6F; }
    .emitido { font-size: 7.4pt; color: #3A5F6F; margin-top: 1.5mm; }
</style>

{{--
    En apaisado la lámina se limita a un ancho fijo en vez de ocupar el 100 %:
    a página completa mide 157 mm de alto y ya no cabe debajo de la ficha del
    paciente, así que bajaba entera a la hoja siguiente y dejaba la primera con
    solo el encabezado.
--}}
@if (($doc['orientacion'] ?? null) === 'apaisada')
    <style>
        .lamina img { width: 200mm; }
    </style>
@endif

<div class="titulo">{{ $doc['titulo'] }}</div>
@if (!empty($doc['subtitulo']))
    <div class="subtitulo">{{ $doc['subtitulo'] }}</div>
@endif

{{-- Ficha del paciente y datos del registro --}}
@php
    $ficha = array_merge($doc['paciente'], $doc['meta']);
    $filasFicha = array_chunk($ficha, 4, true);
@endphp
<table class="ficha" width="100%">
    @foreach ($filasFicha as $fila)
        <tr>
            @foreach ($fila as $etiqueta => $valor)
                <td width="25%">
                    <div class="et">{{ $etiqueta }}</div>
                    <div class="va">{{ $valor }}</div>
                </td>
            @endforeach
            {{-- Rellenar la fila para que las celdas no se estiren --}}
            @for ($i = count($fila); $i < 4; $i++)
                <td width="25%"></td>
            @endfor
        </tr>
    @endforeach
</table>

@foreach ($doc['secciones'] as $seccion)
    @include('reportes.partes.seccion', ['seccion' => $seccion])
@endforeach

{{-- Firma --}}
<div class="firma">
    <div class="firma-linea"></div>
    <div class="firma-nombre">{{ $doc['firma']['nombre'] }}</div>
    @if (!empty($doc['firma']['colegiado']))
        <div class="firma-detalle">Colegiado n.º {{ $doc['firma']['colegiado'] }}</div>
    @endif
    @if (!empty($doc['firma']['registrado_por']) && $doc['firma']['registrado_por'] !== $doc['firma']['nombre'])
        <div class="firma-detalle">Registró: {{ $doc['firma']['registrado_por'] }}</div>
    @endif
    <div class="emitido">Emitido el {{ $doc['firma']['emitido'] }}</div>
</div>
