<?php

namespace Database\Seeders\Demostracion;

use App\Support\MapeoVenoso\Catalogo;

/**
 * Dibuja la lámina PNG de un mapeo venoso a partir de su documento vectorial.
 *
 * En el sistema real el PNG lo exporta el lienzo del editor en el navegador y
 * llega al backend por `POST .../venous-map`. Los datos de demostración se
 * siembran sin navegador, así que la lámina se compone aquí con GD sobre la
 * misma plantilla y el mismo catálogo que usa el editor: mismo vocabulario de
 * colores, mismos trayectos y mismos símbolos.
 *
 * No pretende reproducir el editor píxel a píxel —los símbolos son versiones
 * simplificadas de sus SVG—, sino producir una lámina impresa que se lea igual
 * que la que saldría de una consulta: el dibujo y, debajo, las tablas del
 * reporte que traducen cada trazo y cada marca.
 */
class LaminaMapeo
{
    /**
     * El lienzo se compone a la resolución del viewBox de la plantilla, que es
     * la rejilla en la que están expresados los grosores de trazo. Así un
     * grosor 3 del catálogo mide 3 px aquí y la lámina se imprime con la misma
     * proporción con la que se dibujó.
     */
    private const ANCHO = 1450;

    private const ALTO = 848;

    /** Tipografía de los números de las marcas. La trae mPDF con la imagen. */
    private const FUENTE = 'vendor/mpdf/mpdf/ttfonts/DejaVuSans-Bold.ttf';

    /**
     * Contenido binario del PNG.
     *
     * @param  array<string, mixed>  $documento  Documento vectorial { version, plantilla, objetos }
     */
    public static function png(array $documento): string
    {
        $lienzo = self::lienzo();

        $objetos = $documento['objetos'] ?? [];

        // El orden importa: los recorridos van al fondo y las marcas encima,
        // porque una perforante dibujada bajo el trazo de la safena que la
        // alimenta deja de verse justo donde hay que mirarla.
        foreach (['trazo', 'marcador', 'anotacion', 'texto'] as $tipo) {
            foreach ($objetos as $objeto) {
                if (($objeto['tipo'] ?? null) !== $tipo) {
                    continue;
                }

                match ($tipo) {
                    'trazo' => self::trazo($lienzo, $objeto),
                    'marcador' => self::marcador($lienzo, $objeto),
                    'anotacion' => self::anotacion($lienzo, $objeto),
                    'texto' => self::texto($lienzo, $objeto),
                };
            }
        }

        ob_start();
        imagepng($lienzo);
        $binario = (string) ob_get_clean();

        imagedestroy($lienzo);

        return $binario;
    }

    /*
    |--------------------------------------------------------------------------
    | Lienzo
    |--------------------------------------------------------------------------
    */

    /**
     * La plantilla ampliada al tamaño del viewBox, sobre fondo blanco.
     *
     * El fondo se pinta explícitamente porque la plantilla tiene canal alfa: un
     * PNG con transparencia impreso en PDF sale con el fondo en negro.
     *
     * @return \GdImage
     */
    private static function lienzo()
    {
        $lienzo = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));

        $ruta = Catalogo::rutaPlantilla();

        if ($ruta !== null) {
            $plantilla = imagecreatefrompng($ruta);
            imagecopyresampled(
                $lienzo, $plantilla,
                0, 0, 0, 0,
                self::ANCHO, self::ALTO,
                imagesx($plantilla), imagesy($plantilla)
            );
            imagedestroy($plantilla);
        }

        return $lienzo;
    }

    /*
    |--------------------------------------------------------------------------
    | Objetos
    |--------------------------------------------------------------------------
    */

    /**
     * Recorrido venoso.
     *
     * @param  \GdImage  $lienzo
     * @param  array<string, mixed>  $objeto
     */
    private static function trazo($lienzo, array $objeto): void
    {
        $puntos = $objeto['puntos'] ?? [];

        if (count($puntos) < 2) {
            return;
        }

        $trayecto = Catalogo::trayecto($objeto['trayecto'] ?? null) ?? [];
        $color = self::color($lienzo, $objeto['color'] ?? null);
        $grosor = (float) ($objeto['grosor'] ?? $trayecto['grosor'] ?? 3);

        // El patrón de línea es lo que distingue un trayecto hipoplásico de uno
        // normal, así que se respeta: 'patron' es el stroke-dasharray del
        // catálogo y 'render' los tres patrones que no son guiones.
        $patron = self::patron($trayecto);
        $render = $trayecto['render'] ?? 'linea';

        $camino = self::camino($puntos);

        match ($render) {
            'ondulado' => self::caminoOndulado($lienzo, $camino, $color, $grosor),
            'cruces' => self::caminoCruces($lienzo, $camino, $color, $grosor),
            'doble' => self::caminoDoble($lienzo, $camino, $color, $grosor),
            default => self::caminoLiso($lienzo, $camino, $color, $grosor, $patron),
        };
    }

    /**
     * Hallazgo puntual numerado.
     *
     * @param  \GdImage  $lienzo
     * @param  array<string, mixed>  $objeto
     */
    private static function marcador($lienzo, array $objeto): void
    {
        $marcador = Catalogo::marcador($objeto['marcador'] ?? null) ?? [];
        $color = self::color($lienzo, $objeto['color'] ?? $marcador['color_por_defecto'] ?? 'azul');

        $x = (int) round(((float) $objeto['x']) * self::ANCHO);
        $y = (int) round(((float) $objeto['y']) * self::ALTO);
        $d = (int) ($marcador['tamano'] ?? 16);

        match ($objeto['marcador'] ?? null) {
            'golfo_venoso' => self::golfo($lienzo, $x, $y, $d, $color),
            'no_venosa' => self::elipse($lienzo, $x, $y, $d, $color),
            'safenectomia' => self::escalera($lienzo, $x, $y, $d, $color),
            'ulcera' => self::ulcera($lienzo, $x, $y, $d, $color),
            default => self::aro($lienzo, $x, $y, $d, $color),
        };

        if (isset($objeto['numero'])) {
            self::rotulo($lienzo, (string) $objeto['numero'], $x + $d, $y - $d, $color);
        }
    }

    /**
     * Anotación anclada: su número en un círculo y el texto al lado.
     *
     * @param  \GdImage  $lienzo
     * @param  array<string, mixed>  $objeto
     */
    private static function anotacion($lienzo, array $objeto): void
    {
        $color = self::color($lienzo, $objeto['color'] ?? 'negro');

        $x = (int) round(((float) $objeto['x']) * self::ANCHO);
        $y = (int) round(((float) $objeto['y']) * self::ALTO);

        self::aro($lienzo, $x, $y, 20, $color);

        if (isset($objeto['numero'])) {
            self::rotulo($lienzo, (string) $objeto['numero'], $x - 5, $y + 6, $color, 13);
        }
    }

    /**
     * Rótulo suelto del dibujo.
     *
     * @param  \GdImage  $lienzo
     * @param  array<string, mixed>  $objeto
     */
    private static function texto($lienzo, array $objeto): void
    {
        $color = self::color($lienzo, $objeto['color'] ?? 'negro');

        $x = (int) round(((float) $objeto['x']) * self::ANCHO);
        $y = (int) round(((float) $objeto['y']) * self::ALTO);

        self::rotulo($lienzo, (string) ($objeto['texto'] ?? ''), $x, $y, $color, (int) ($objeto['tamano'] ?? 16));
    }

    /*
    |--------------------------------------------------------------------------
    | Trazado
    |--------------------------------------------------------------------------
    |
    | Las líneas gruesas de GD son rectángulos sin extremos redondeados: en un
    | recorrido quebrado dejan muescas en cada vértice. Se pintan interpolando
    | discos a lo largo del camino, que además da el mismo extremo redondeado
    | que usa el editor.
    |
    */

    /**
     * Puntos normalizados convertidos a píxeles del lienzo.
     *
     * @param  array<int, array<int, float>>  $puntos
     * @return array<int, array{0: float, 1: float}>
     */
    private static function camino(array $puntos): array
    {
        return array_map(
            fn (array $p) => [((float) $p[0]) * self::ANCHO, ((float) $p[1]) * self::ALTO],
            $puntos
        );
    }

    /**
     * @param  \GdImage  $lienzo
     * @param  array<int, array{0: float, 1: float}>  $camino
     * @param  array{0: float, 1: float}|null  $patron  [trazo, hueco] en píxeles
     */
    private static function caminoLiso($lienzo, array $camino, int $color, float $grosor, ?array $patron): void
    {
        $d = max(1, (int) round($grosor));
        $recorrido = 0.0;

        foreach (self::pasos($camino) as [$x, $y, $avance]) {
            $recorrido += $avance;

            if ($patron !== null) {
                $ciclo = $patron[0] + $patron[1];
                if (fmod($recorrido, $ciclo) > $patron[0]) {
                    continue;   // estamos en el hueco del guión
                }
            }

            imagefilledellipse($lienzo, (int) round($x), (int) round($y), $d, $d, $color);
        }
    }

    /**
     * Trayecto epifascial: una onda sobre el recorrido.
     *
     * @param  \GdImage  $lienzo
     * @param  array<int, array{0: float, 1: float}>  $camino
     */
    private static function caminoOndulado($lienzo, array $camino, int $color, float $grosor): void
    {
        $d = max(1, (int) round($grosor));
        $recorrido = 0.0;
        $amplitud = 4.5;
        $periodo = 18.0;

        foreach (self::pasos($camino) as [$x, $y, $avance, $nx, $ny]) {
            $recorrido += $avance;
            $desvio = $amplitud * sin(2 * M_PI * $recorrido / $periodo);

            imagefilledellipse(
                $lienzo,
                (int) round($x + $nx * $desvio),
                (int) round($y + $ny * $desvio),
                $d, $d, $color
            );
        }
    }

    /**
     * Trayecto con adherencias: una cadena de equis.
     *
     * @param  \GdImage  $lienzo
     * @param  array<int, array{0: float, 1: float}>  $camino
     */
    private static function caminoCruces($lienzo, array $camino, int $color, float $grosor): void
    {
        $recorrido = 0.0;
        $siguiente = 0.0;
        $brazo = 5.0;

        imagesetthickness($lienzo, max(1, (int) round($grosor)));

        foreach (self::pasos($camino) as [$x, $y, $avance]) {
            $recorrido += $avance;

            if ($recorrido < $siguiente) {
                continue;
            }

            $siguiente = $recorrido + 13.0;

            imageline($lienzo, (int) ($x - $brazo), (int) ($y - $brazo), (int) ($x + $brazo), (int) ($y + $brazo), $color);
            imageline($lienzo, (int) ($x - $brazo), (int) ($y + $brazo), (int) ($x + $brazo), (int) ($y - $brazo), $color);
        }

        imagesetthickness($lienzo, 1);
    }

    /**
     * Engrosamiento de pared: dos líneas paralelas.
     *
     * @param  \GdImage  $lienzo
     * @param  array<int, array{0: float, 1: float}>  $camino
     */
    private static function caminoDoble($lienzo, array $camino, int $color, float $grosor): void
    {
        $d = max(1, (int) round($grosor));
        $separacion = 3.0;

        foreach (self::pasos($camino) as [$x, $y, , $nx, $ny]) {
            imagefilledellipse($lienzo, (int) round($x + $nx * $separacion), (int) round($y + $ny * $separacion), $d, $d, $color);
            imagefilledellipse($lienzo, (int) round($x - $nx * $separacion), (int) round($y - $ny * $separacion), $d, $d, $color);
        }
    }

    /**
     * Recorre el camino punto a punto, de píxel en píxel.
     *
     * Devuelve, para cada paso, su posición, cuánto avanzó y la normal del
     * segmento, que es lo que necesitan la onda y la línea doble para
     * separarse del eje del recorrido.
     *
     * @param  array<int, array{0: float, 1: float}>  $camino
     * @return \Generator<int, array{0: float, 1: float, 2: float, 3: float, 4: float}>
     */
    private static function pasos(array $camino): \Generator
    {
        $paso = 0.6;

        for ($i = 0; $i < count($camino) - 1; $i++) {
            [$x0, $y0] = $camino[$i];
            [$x1, $y1] = $camino[$i + 1];

            $dx = $x1 - $x0;
            $dy = $y1 - $y0;
            $largo = sqrt($dx * $dx + $dy * $dy);

            if ($largo < 0.001) {
                continue;
            }

            // Normal unitaria del segmento
            $nx = -$dy / $largo;
            $ny = $dx / $largo;

            for ($t = 0.0; $t <= $largo; $t += $paso) {
                yield [$x0 + $dx * $t / $largo, $y0 + $dy * $t / $largo, $paso, $nx, $ny];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Símbolos
    |--------------------------------------------------------------------------
    |
    | Versiones en GD de los SVG del catálogo. Se dibujan con el mismo tamaño
    | que declara cada marcador para que dos hallazgos iguales se impriman
    | iguales, que es la razón por la que ese tamaño no lo elige el médico.
    |
    */

    /** @param \GdImage $lienzo */
    private static function aro($lienzo, int $x, int $y, int $d, int $color): void
    {
        imagesetthickness($lienzo, 3);
        imageellipse($lienzo, $x, $y, $d, $d, $color);
        imagesetthickness($lienzo, 1);
    }

    /** @param \GdImage $lienzo */
    private static function golfo($lienzo, int $x, int $y, int $d, int $color): void
    {
        imagesetthickness($lienzo, 3);
        imageline($lienzo, $x - $d, $y, $x + $d, $y, $color);
        imagesetthickness($lienzo, 1);
        imagefilledellipse($lienzo, $x, $y, (int) round($d * 0.7), (int) round($d * 0.7), $color);
    }

    /** @param \GdImage $lienzo */
    private static function elipse($lienzo, int $x, int $y, int $d, int $color): void
    {
        imagesetthickness($lienzo, 3);
        imageellipse($lienzo, $x, $y, (int) round($d * 1.5), (int) round($d * 0.75), $color);
        imagesetthickness($lienzo, 1);
    }

    /** @param \GdImage $lienzo */
    private static function escalera($lienzo, int $x, int $y, int $d, int $color): void
    {
        $mitad = (int) round($d / 2);

        imagesetthickness($lienzo, 3);
        imageline($lienzo, $x - $mitad, $y, $x + $mitad, $y, $color);

        for ($i = -1; $i <= 1; $i++) {
            $px = $x + $i * (int) round($d / 3);
            imageline($lienzo, $px, $y - $mitad, $px, $y + $mitad, $color);
        }

        imagesetthickness($lienzo, 1);
    }

    /** @param \GdImage $lienzo */
    private static function ulcera($lienzo, int $x, int $y, int $d, int $color): void
    {
        imagesetthickness($lienzo, 3);
        imageellipse($lienzo, $x, $y, $d, $d, $color);
        imagesetthickness($lienzo, 2);

        // El rayado interior, que es lo que distingue la úlcera de una marca
        // cualquiera en una impresión en blanco y negro.
        $r = (int) round($d / 2);
        for ($i = -$r + 4; $i < $r; $i += 5) {
            $c = (int) round(sqrt(max(0, $r * $r - $i * $i)));
            imageline($lienzo, $x - $c, $y + $i, $x + $c, $y + $i, $color);
        }

        imagesetthickness($lienzo, 1);
    }

    /**
     * Número o rótulo junto a una marca.
     *
     * @param  \GdImage  $lienzo
     */
    private static function rotulo($lienzo, string $texto, int $x, int $y, int $color, int $tamano = 15): void
    {
        if ($texto === '') {
            return;
        }

        $fuente = base_path(self::FUENTE);

        if (is_file($fuente)) {
            imagettftext($lienzo, $tamano, 0, $x, $y, $color, $fuente, $texto);

            return;
        }

        imagestring($lienzo, 5, $x, $y - 14, $texto, $color);
    }

    /*
    |--------------------------------------------------------------------------
    | Color
    |--------------------------------------------------------------------------
    */

    /**
     * Color del catálogo ('rojo') o hexadecimal, tal como los archiva el editor.
     *
     * @param  \GdImage  $lienzo
     */
    private static function color($lienzo, ?string $color): int
    {
        $hex = $color !== null && str_starts_with($color, '#')
            ? $color
            : (Catalogo::hexColor($color) ?? '#1A1A1A');

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return imagecolorallocate($lienzo, (int) $r, (int) $g, (int) $b);
    }

    /**
     * El stroke-dasharray del catálogo ('9 7') en píxeles del lienzo.
     *
     * @param  array<string, mixed>  $trayecto
     * @return array{0: float, 1: float}|null
     */
    private static function patron(array $trayecto): ?array
    {
        $patron = $trayecto['patron'] ?? null;

        if (! is_string($patron)) {
            return null;
        }

        $partes = array_map('floatval', preg_split('/\s+/', trim($patron)) ?: []);

        return count($partes) >= 2 ? [$partes[0], $partes[1]] : null;
    }
}
