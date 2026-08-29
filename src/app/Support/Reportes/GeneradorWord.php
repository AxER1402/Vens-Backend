<?php

namespace App\Support\Reportes;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * Renderiza a .docx el mismo documento que el generador de PDF.
 *
 * Se construye con la API de PhpWord en lugar de importar el HTML del PDF: el
 * lector de HTML de PhpWord descarta casi todo el CSS y maltrata las tablas, que
 * es justo lo que llevan estos informes. Y en lugar de una plantilla .docx con
 * marcadores, porque casi todas las secciones son listas de longitud variable y
 * clonar filas a mano sobre un binario es más frágil que escribirlas.
 *
 * Lo que se comparte con el PDF es la capa que importa: el contenido ya resuelto
 * por los constructores de datos.
 *
 * **Cuidado con los bordes de las tablas.** `borderSize => 0` no deja una tabla
 * sin bordes: emite <w:top w:val="single" w:sz="0"/>, un borde simple de grosor
 * cero que Word y LibreOffice dibujan como línea fina. El informe salía
 * cuadriculado de arriba abajo —incluido un recuadro alrededor del membrete—
 * por esto y no por usar tablas. Para que no haya borde hay que **omitir todas
 * las claves de borde**, y entonces no se escribe <w:tblBorders>.
 *
 * Las tablas replican la misma rejilla que el PDF, que es lo que hace que los
 * dos documentos se lean igual.
 */
class GeneradorWord
{
    /** Paleta del informe, la misma del PDF. */
    private const AZUL = '243757';

    private const TEAL = '0C7D8C';

    private const GRIS = '3A5F6F';

    private const FONDO = 'F4F7F8';

    private const LINEA = 'DCE4E8';

    /**
     * Componer el .docx y devolver sus bytes.
     *
     * @param  array<string, mixed>  $doc
     */
    public function generar(array $doc): string
    {
        $word = new PhpWord;
        $word->getSettings()->setUpdateFields(true);
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);

        $seccion = $word->addSection([
            'marginLeft' => Converter::cmToTwip(1.8),
            'marginRight' => Converter::cmToTwip(1.8),
            'marginTop' => Converter::cmToTwip(1.6),
            'marginBottom' => Converter::cmToTwip(1.6),
        ]);

        $this->membrete($seccion);
        $this->pie($seccion, $doc);

        $this->titulo($seccion, $doc);
        $this->ficha($seccion, $doc);

        foreach ($doc['secciones'] as $bloque) {
            $this->bloque($seccion, $bloque);
        }

        $this->firma($seccion, $doc);

        return $this->bytes($word);
    }

    /*
    |--------------------------------------------------------------------------
    | Membrete y pie
    |--------------------------------------------------------------------------
    */

    private function membrete(Section $seccion): void
    {
        $centro = config('reportes.centro');
        $encabezado = $seccion->addHeader();

        // Sin ninguna clave de borde: `borderSize => 0` no quita el borde, emite
        // <w:top w:val="single" w:sz="0"/>, que Word dibuja como una línea fina.
        // Omitirlas todas es lo que hace que no se escriba <w:tblBorders>.
        $tabla = $encabezado->addTable(['cellMargin' => 0, 'width' => 100 * 50, 'unit' => 'pct']);
        $tabla->addRow();

        $logo = $this->rutaLogo();

        if ($logo) {
            $celda = $tabla->addCell(2500);
            $celda->addImage($logo, ['width' => 88, 'alignment' => Jc::START]);
        }

        // El nombre de la clínica es largo: esta columna se lleva el ancho que
        // haga falta para que no parta en dos líneas.
        $datos = $tabla->addCell($logo ? 5000 : 7500);
        $datos->addText(htmlspecialchars($centro['nombre']), ['bold' => true, 'size' => 10.5, 'color' => self::AZUL], ['spaceAfter' => 0]);

        if ($centro['especialidad']) {
            $datos->addText(htmlspecialchars($centro['especialidad']), ['size' => 8, 'color' => self::GRIS]);
        }

        $contacto = $tabla->addCell(2500);

        foreach (array_filter([
            $centro['direccion'],
            $centro['telefono'] ? 'Tel. '.$centro['telefono'] : null,
            $centro['correo'],
        ]) as $linea) {
            $contacto->addText(htmlspecialchars($linea), ['size' => 7.5, 'color' => self::GRIS], ['alignment' => Jc::END, 'spaceAfter' => 0]);
        }

        $encabezado->addTextBreak(0);
        $encabezado->addText('', [], ['borderBottomSize' => 6, 'borderBottomColor' => self::TEAL]);
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    private function pie(Section $seccion, array $doc): void
    {
        $pie = $seccion->addFooter();
        $texto = $pie->addTextRun(['alignment' => Jc::CENTER, 'spaceBefore' => 0]);

        $estilo = ['size' => 7.5, 'color' => self::GRIS];

        $texto->addText(htmlspecialchars(config('reportes.centro.nombre').' · '.$doc['titulo'].' · Página '), $estilo);
        $texto->addField('PAGE', [], [], null);
        $texto->addText(' de ', $estilo);
        $texto->addField('NUMPAGES', [], [], null);
    }

    /*
    |--------------------------------------------------------------------------
    | Cuerpo
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $doc
     */
    private function titulo(Section $seccion, array $doc): void
    {
        // Un borrador se puede imprimir, pero tiene que decir que lo es. Word no
        // tiene marca de agua por API, así que se avisa en el propio título.
        if (! empty($doc['borrador'])) {
            $seccion->addText(
                'DOCUMENTO EN BORRADOR — NO ES UN INFORME DEFINITIVO',
                ['bold' => true, 'size' => 9, 'color' => 'B43C32'],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 60]
            );
        }

        $seccion->addText(
            htmlspecialchars($doc['titulo']),
            ['bold' => true, 'size' => 14, 'color' => self::AZUL],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );

        if (! empty($doc['subtitulo'])) {
            $seccion->addText(
                htmlspecialchars($doc['subtitulo']),
                ['size' => 9, 'color' => self::GRIS],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 160]
            );
        } else {
            $seccion->addTextBreak(1);
        }
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    private function ficha(Section $seccion, array $doc): void
    {
        $ficha = array_merge($doc['paciente'], $doc['meta']);

        $tabla = $seccion->addTable([
            'borderSize' => 4,
            'borderColor' => 'C9D4DA',
            'cellMargin' => 80,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        foreach (array_chunk($ficha, 4, true) as $fila) {
            $tabla->addRow();

            foreach ($fila as $etiqueta => $valor) {
                $celda = $tabla->addCell(2400, ['bgColor' => self::FONDO]);
                $celda->addText(htmlspecialchars(mb_strtoupper($etiqueta)), ['size' => 7, 'color' => self::GRIS], ['spaceAfter' => 0]);
                $celda->addText(htmlspecialchars($valor), ['size' => 9.5, 'bold' => true], ['spaceAfter' => 0]);
            }

            for ($i = count($fila); $i < 4; $i++) {
                $tabla->addCell(2400, ['bgColor' => self::FONDO])->addText('');
            }
        }

        $seccion->addTextBreak(1);
    }

    /**
     * @param  array<string, mixed>  $bloque
     */
    private function bloque(Section $seccion, array $bloque): void
    {
        if (! empty($bloque['salto'])) {
            $seccion->addPageBreak();
        }

        $tipo = $bloque['tipo'] ?? 'campos';

        // La portada de anexo se pinta ella misma; el resto lleva epígrafe
        if (! empty($bloque['titulo']) && $tipo !== 'anexo') {
            $seccion->addText(
                htmlspecialchars(mb_strtoupper($bloque['titulo'])),
                ['bold' => true, 'size' => 9, 'color' => self::TEAL],
                // keepNext ata el epígrafe a lo que viene detrás: sin él Word
                // deja el título solo al final de una página y su tabla en la
                // siguiente.
                ['spaceBefore' => 160, 'spaceAfter' => 60, 'borderBottomSize' => 6, 'borderBottomColor' => self::TEAL, 'keepNext' => true]
            );
        }

        match ($tipo) {
            'campos' => $this->campos($seccion, $bloque),
            'tabla' => $this->tabla($seccion, $bloque),
            'texto' => $this->texto($seccion, $bloque),
            'imagen' => $this->imagen($seccion, $bloque),
            'anexo' => $this->portadaAnexo($seccion, $bloque),
            default => null,
        };

        if (! empty($bloque['extra'])) {
            $this->bloque($seccion, $bloque['extra']);
        }

        foreach ($bloque['bloques'] ?? [] as $anidado) {
            $this->bloque($seccion, $anidado);
        }
    }

    /**
     * Pares etiqueta/valor en la misma rejilla de dos columnas que el PDF.
     *
     * La tabla no lleva **ninguna** clave de borde. Es deliberado: `borderSize => 0`
     * no deja la tabla sin bordes, emite <w:top w:val="single" w:sz="0"/> —un borde
     * simple de grosor cero— que Word dibuja como línea fina. Sin esas claves no se
     * escribe <w:tblBorders> y la rejilla queda de verdad invisible al imprimir.
     *
     * @param  array<string, mixed>  $bloque
     */
    private function campos(Section $seccion, array $bloque): void
    {
        $tabla = $seccion->addTable(['cellMargin' => 40, 'width' => 100 * 50, 'unit' => 'pct']);

        foreach (array_chunk($bloque['campos'], 2, true) as $fila) {
            $tabla->addRow();

            foreach ($fila as $etiqueta => $valor) {
                $tabla->addCell(2200)->addText(htmlspecialchars($etiqueta), ['size' => 8, 'color' => self::GRIS], ['spaceAfter' => 0]);
                $tabla->addCell(2800)->addText(htmlspecialchars($valor), ['size' => 9], ['spaceAfter' => 0]);
            }

            for ($i = count($fila); $i < 2; $i++) {
                $tabla->addCell(2200)->addText('');
                $tabla->addCell(2800)->addText('');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $bloque
     */
    private function tabla(Section $seccion, array $bloque): void
    {
        $tabla = $seccion->addTable([
            'borderSize' => 4,
            'borderColor' => self::LINEA,
            'cellMargin' => 60,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        $anchos = $bloque['anchos'] ?? [];
        $total = 9600;

        $tabla->addRow(null, ['tblHeader' => true]);

        foreach ($bloque['encabezados'] as $i => $encabezado) {
            $ancho = isset($anchos[$i]) ? (int) round($total * $anchos[$i] / 100) : (int) ($total / count($bloque['encabezados']));
            $celda = $tabla->addCell($ancho, ['bgColor' => self::AZUL]);
            $celda->addText(htmlspecialchars($encabezado), ['bold' => true, 'size' => 8, 'color' => 'FFFFFF'], ['spaceAfter' => 0]);
        }

        foreach ($bloque['filas'] as $n => $fila) {
            $tabla->addRow();
            $fondo = $n % 2 ? ['bgColor' => 'F7FAFB'] : [];

            foreach (array_values($fila) as $i => $celda) {
                $ancho = isset($anchos[$i]) ? (int) round($total * $anchos[$i] / 100) : (int) ($total / count($fila));
                $tabla->addCell($ancho, $fondo)->addText(htmlspecialchars((string) $celda), ['size' => 8.5], ['spaceAfter' => 0]);
            }
        }
    }

    /**
     * Portada de anexo: marca dónde termina la consulta y empieza lo adjunto.
     *
     * @param  array<string, mixed>  $bloque
     */
    private function portadaAnexo(Section $seccion, array $bloque): void
    {
        $seccion->addText(
            htmlspecialchars($bloque['titulo']),
            ['bold' => true, 'size' => 11.5, 'color' => self::AZUL],
            [
                'spaceBefore' => 0,
                'spaceAfter' => 180,
                'shading' => ['fill' => 'EDF3F5'],
                'borderLeftSize' => 18,
                'borderLeftColor' => self::TEAL,
                'indentation' => ['left' => 120],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $bloque
     */
    private function texto(Section $seccion, array $bloque): void
    {
        foreach (preg_split('/\R/', (string) $bloque['texto']) as $parrafo) {
            $seccion->addText(htmlspecialchars($parrafo), ['size' => 9.5], ['alignment' => Jc::BOTH, 'spaceAfter' => 40]);
        }
    }

    /**
     * @param  array<string, mixed>  $bloque
     */
    private function imagen(Section $seccion, array $bloque): void
    {
        if (empty($bloque['ruta']) || ! is_file($bloque['ruta'])) {
            return;
        }

        $seccion->addImage($bloque['ruta'], [
            'width' => 460,
            'alignment' => Jc::CENTER,
            'wrappingStyle' => 'inline',
        ]);

        if (! empty($bloque['pie'])) {
            $seccion->addText(
                htmlspecialchars($bloque['pie']),
                ['size' => 7.5, 'color' => self::GRIS],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    private function firma(Section $seccion, array $doc): void
    {
        $seccion->addTextBreak(3);
        $seccion->addText('', [], ['borderTopSize' => 6, 'borderTopColor' => '1B2A42', 'spaceAfter' => 0]);

        $seccion->addText(htmlspecialchars($doc['firma']['nombre']), ['bold' => true, 'size' => 9.5], ['spaceAfter' => 0]);

        if (! empty($doc['firma']['colegiado'])) {
            $seccion->addText('Colegiado n.º '.htmlspecialchars($doc['firma']['colegiado']), ['size' => 8, 'color' => self::GRIS], ['spaceAfter' => 0]);
        }

        if (! empty($doc['firma']['registrado_por']) && $doc['firma']['registrado_por'] !== $doc['firma']['nombre']) {
            $seccion->addText('Registró: '.htmlspecialchars($doc['firma']['registrado_por']), ['size' => 8, 'color' => self::GRIS], ['spaceAfter' => 0]);
        }

        $seccion->addText('Emitido el '.htmlspecialchars($doc['firma']['emitido']), ['size' => 7.5, 'color' => self::GRIS]);
    }

    /*
    |--------------------------------------------------------------------------
    | Utilidades
    |--------------------------------------------------------------------------
    */

    /**
     * PhpWord solo sabe escribir a un archivo o a php://output, así que el .docx
     * se compone en un temporal y se lee de vuelta.
     */
    private function bytes(PhpWord $word): string
    {
        $dir = config('reportes.temp_dir');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $temporal = tempnam($dir, 'docx');

        try {
            (new Word2007($word))->save($temporal);

            return (string) file_get_contents($temporal);
        } finally {
            @unlink($temporal);
        }
    }

    private function rutaLogo(): ?string
    {
        $relativa = config('reportes.centro.logo');

        if (! $relativa) {
            return null;
        }

        $ruta = public_path($relativa);

        return is_file($ruta) ? $ruta : null;
    }
}
