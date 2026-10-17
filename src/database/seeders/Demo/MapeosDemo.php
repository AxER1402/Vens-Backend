<?php

namespace Database\Seeders\Demo;

use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Support\MapeoVenoso\Catalogo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mapeos venosos ficticios para la demo.
 *
 * Cada mapeo se guarda igual que lo guarda el editor: el documento vectorial
 * (para reabrirlo y seguir editándolo) y el PNG sobre la plantilla (para el
 * PDF). Los trayectos siguen la anatomía de la lámina y el color sale de lo
 * que informa el Ecodöppler de cada paciente: rojo donde hay reflujo, azul
 * donde la vena es competente.
 */
class MapeosDemo
{
    private const ESCALA = 2;

    /** Safena mayor en la vista antero-interna de cada miembro, de la ingle al maléolo. */
    private const SAFENA_MAYOR = [
        'izq' => [[0.278, 0.26], [0.276, 0.32], [0.272, 0.40], [0.268, 0.48], [0.265, 0.55], [0.266, 0.62], [0.268, 0.70], [0.272, 0.77], [0.278, 0.83]],
        'der' => [[0.722, 0.26], [0.725, 0.32], [0.729, 0.40], [0.733, 0.48], [0.736, 0.55], [0.735, 0.62], [0.733, 0.70], [0.730, 0.77], [0.727, 0.83]],
    ];

    /** Safena menor en la vista posterior, del hueco poplíteo al maléolo externo. */
    private const SAFENA_MENOR = [
        'izq' => [[0.158, 0.575], [0.160, 0.62], [0.161, 0.68], [0.159, 0.74], [0.157, 0.79], [0.156, 0.83]],
        'der' => [[0.858, 0.58], [0.856, 0.63], [0.853, 0.69], [0.851, 0.75], [0.853, 0.80], [0.857, 0.835]],
    ];

    /**
     * Los mapeos de la demo, por paciente. La clave es el teléfono ficticio con
     * el que DemoSeeder crea a cada uno.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function porTelefono(): array
    {
        $m = new self;

        return [
            // María Fernanda: safena mayor bilateral, peor la izquierda
            '55218764' => [
                ...$m->safenaMayor('izq', [[0.26, 0.70, 'rojo'], [0.70, 0.83, 'azul']]),
                ...$m->safenaMayor('der', [[0.26, 0.55, 'rojo'], [0.55, 0.83, 'azul']]),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['izq']),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['der']),
                $m->trazo('epifascial', 'rojo', [[0.268, 0.47], [0.276, 0.51], [0.283, 0.55], [0.286, 0.59]]),
                $m->trazo('epifascial', 'rojo', [[0.731, 0.42], [0.722, 0.46], [0.715, 0.50], [0.716, 0.54]]),
                $m->marcador('perforante', 'rojo', 0.270, 0.42),
                $m->anotacion('Reflujo en unión safenofemoral de 1.8 s.', 0.300, 0.27),
                $m->anotacion('Reflujo en unión safenofemoral de 0.9 s.', 0.705, 0.30),
            ],

            // Ana Lucía: telangiectasias en la cara lateral de ambos muslos
            '42917355' => [
                ...$m->arana(0.395, 0.36),
                ...$m->arana(0.410, 0.43),
                ...$m->arana(0.600, 0.37),
                ...$m->arana(0.590, 0.44),
                $m->anotacion('Telangiectasias y reticulares en cara lateral de muslo.', 0.420, 0.33),
                $m->anotacion('Mismo patrón que el miembro izquierdo.', 0.615, 0.33),
            ],

            // Rosa Elvira: insuficiencia safena izquierda con Cockett y dermatitis ocre
            '57340219' => [
                ...$m->safenaMayor('izq', [[0.26, 0.83, 'rojo']]),
                $m->trazo('safena_externa', 'rojo', self::SAFENA_MENOR['izq']),
                $m->trazo('safena_interna', 'azul', self::SAFENA_MAYOR['der']),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['der']),
                $m->trazo('epifascial', 'rojo', [[0.268, 0.66], [0.276, 0.70], [0.281, 0.74], [0.280, 0.78]]),
                $m->marcador('perforante', 'rojo', 0.270, 0.76),
                $m->marcador('golfo_venoso', 'rojo', 0.266, 0.62),
                $m->anotacion('Dermatitis ocre en tercio distal de pierna.', 0.305, 0.80),
                $m->anotacion('Edema con fóvea en tobillo.', 0.157, 0.86),
            ],

            // Carlos Enrique: safena menor derecha incompetente desde el poplíteo
            '30482617' => [
                $m->trazo('safena_externa', 'rojo', self::SAFENA_MENOR['der']),
                $m->trazo('epifascial', 'rojo', [[0.855, 0.64], [0.848, 0.67], [0.842, 0.70], [0.840, 0.73]]),
                $m->trazo('safena_interna', 'azul', self::SAFENA_MAYOR['der']),
                $m->trazo('safena_interna', 'azul', self::SAFENA_MAYOR['izq']),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['izq']),
                $m->marcador('golfo_venoso', 'rojo', 0.857, 0.585),
                $m->anotacion('Unión safenopoplítea incompetente, 6.8 mm.', 0.870, 0.56),
            ],

            // Gabriela Sofía: telangiectasias en hueco poplíteo y cara lateral de pantorrillas
            '48125590' => [
                ...$m->arana(0.160, 0.58),
                ...$m->arana(0.855, 0.59),
                ...$m->arana(0.415, 0.66),
                ...$m->arana(0.590, 0.67),
                $m->anotacion('Telangiectasias en hueco poplíteo.', 0.165, 0.54),
                $m->anotacion('Telangiectasias en cara lateral de pantorrilla.', 0.595, 0.62),
            ],

            // Lucía Beatriz: reflujo segmentario de safena izquierda desde Hunter
            '59063482' => [
                ...$m->safenaMayor('izq', [[0.26, 0.38, 'azul'], [0.38, 0.83, 'rojo']]),
                $m->trazo('safena_interna', 'azul', self::SAFENA_MAYOR['der']),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['izq']),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['der']),
                $m->trazo('epifascial', 'rojo', [[0.267, 0.61], [0.276, 0.65], [0.281, 0.69], [0.279, 0.73]]),
                $m->marcador('perforante', 'rojo', 0.271, 0.40),
                $m->anotacion('Perforante de Hunter incompetente, 4.0 mm.', 0.300, 0.40),
                $m->anotacion('Paquete varicoso doloroso, sin flebitis.', 0.280, 0.67),
            ],

            // Jorge Luis: insuficiencia leve a moderada de safena mayor bilateral
            '54790138' => [
                ...$m->safenaMayor('izq', [[0.26, 0.70, 'rojo'], [0.70, 0.83, 'azul']]),
                ...$m->safenaMayor('der', [[0.26, 0.55, 'rojo'], [0.55, 0.83, 'azul']]),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['izq']),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['der']),
                $m->anotacion('Edema bilateral con fóvea leve.', 0.275, 0.86),
                $m->anotacion('Sin trombosis venosa profunda.', 0.730, 0.86),
            ],

            // Marta Julia: safenectomía izquierda (2012) y reticulares en pierna derecha
            '41639027' => [
                ...$m->safenaMayor('izq', [[0.26, 0.70, 'gris', 'aplasico']]),
                $m->trazo('safena_interna', 'azul', self::SAFENA_MAYOR['der']),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['der']),
                $m->trazo('safena_externa', 'azul', self::SAFENA_MENOR['izq']),
                $m->marcador('safenectomia', 'gris', 0.277, 0.27),
                ...$m->arana(0.585, 0.64),
                ...$m->arana(0.598, 0.71),
                $m->anotacion('Safenectomía izquierda en 2012.', 0.300, 0.30),
                $m->anotacion('Venas reticulares en cara lateral de pierna.', 0.610, 0.60),
            ],
        ];
    }

    /**
     * Nombre con el que DemoSeeder crea a cada paciente, por si en la demo se
     * le cambió el teléfono.
     */
    private const NOMBRES = [
        '55218764' => 'María Fernanda',
        '42917355' => 'Ana Lucía',
        '57340219' => 'Rosa Elvira',
        '30482617' => 'Carlos Enrique',
        '48125590' => 'Gabriela Sofía',
        '59063482' => 'Lucía Beatriz',
        '54790138' => 'Jorge Luis',
        '41639027' => 'Marta Julia',
    ];

    /**
     * El mapeo que le toca a un paciente de la demo, o null si no es uno de ellos.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function para(Patient $paciente): ?array
    {
        $mapeos = self::porTelefono();

        if (isset($mapeos[$paciente->telefono])) {
            return $mapeos[$paciente->telefono];
        }

        foreach (self::NOMBRES as $telefono => $nombre) {
            if (str_starts_with($paciente->nombre, $nombre)) {
                return $mapeos[$telefono];
            }
        }

        return null;
    }

    /**
     * Guarda el mapeo en la consulta tal como lo haría el editor.
     *
     * @param  array<int, array<string, mixed>>  $objetos
     */
    public static function guardar(ClinicalHistory $historia, array $objetos): void
    {
        $objetos = self::completar($objetos);

        $ruta = "mapeos-venosos/{$historia->id}/".Str::uuid()->toString().'.png';
        Storage::disk('public')->put($ruta, self::dibujar($objetos));

        $historia->forceFill([
            'mapeo_venoso_path' => $ruta,
            'mapeo_venoso_datos' => [
                'version' => 1,
                'plantilla' => Catalogo::plantillaId(),
                'objetos' => $objetos,
            ],
            'mapeo_venoso_updated_at' => now(),
        ])->saveQuietly();
    }

    /*
    |--------------------------------------------------------------------------
    | Objetos del documento
    |--------------------------------------------------------------------------
    */

    /**
     * La safena mayor de un miembro partida en tramos, cada uno con su color.
     * Cada tramo es [desde y, hasta y, color] o [desde, hasta, color, trayecto].
     *
     * @param  array<int, array<int, mixed>>  $tramos
     * @return array<int, array<string, mixed>>
     */
    private function safenaMayor(string $lado, array $tramos): array
    {
        $trazos = [];

        foreach ($tramos as $tramo) {
            [$desde, $hasta, $color] = $tramo;
            $trayecto = $tramo[3] ?? 'safena_interna';

            $puntos = array_values(array_filter(
                self::suavizar(self::SAFENA_MAYOR[$lado]),
                fn ($p) => $p[1] >= $desde - 0.0001 && $p[1] <= $hasta + 0.0001
            ));

            $trazos[] = $this->trazo($trayecto, $color, $puntos, suavizar: false);
        }

        return $trazos;
    }

    /**
     * Un trazo a mano alzada: la curva se densifica para que tenga la soltura
     * del dibujo en la tableta y no la de una línea quebrada.
     *
     * @param  array<int, array<int, float>>  $puntos
     * @return array<string, mixed>
     */
    private function trazo(string $trayecto, string $color, array $puntos, bool $suavizar = true): array
    {
        $estilo = Catalogo::trayecto($trayecto);

        return [
            'tipo' => 'trazo',
            'color' => $color,
            'trayecto' => $trayecto,
            'grosor' => $estilo['grosor'] ?? 3,
            'puntos' => $suavizar ? self::suavizar($puntos) : $puntos,
        ];
    }

    /**
     * Arañas vasculares: tres o cuatro trazos cortos y finos que salen de un
     * mismo punto, como las dibuja el médico sobre la lámina.
     *
     * @return array<int, array<string, mixed>>
     */
    private function arana(float $x, float $y): array
    {
        $ramas = [
            [[0, 0], [-0.006, -0.012], [-0.004, -0.024]],
            [[0, 0], [0.007, -0.008], [0.012, -0.018]],
            [[0, 0], [0.005, 0.011], [0.011, 0.020]],
            [[0, 0], [-0.006, 0.009], [-0.010, 0.019]],
        ];

        return array_map(fn ($rama) => [
            'tipo' => 'trazo',
            'color' => 'rojo',
            'trayecto' => 'epifascial',
            'grosor' => 1.5,
            'puntos' => self::suavizar(array_map(fn ($p) => [$x + $p[0], $y + $p[1]], $rama)),
        ], $ramas);
    }

    /**
     * @return array<string, mixed>
     */
    private function marcador(string $marcador, string $color, float $x, float $y): array
    {
        return ['tipo' => 'marcador', 'marcador' => $marcador, 'color' => $color, 'x' => $x, 'y' => $y];
    }

    /**
     * @return array<string, mixed>
     */
    private function anotacion(string $texto, float $x, float $y): array
    {
        return ['tipo' => 'anotacion', 'texto' => $texto, 'x' => $x, 'y' => $y];
    }

    /**
     * Lo que el editor agrega al archivar: id, número correlativo y zona.
     *
     * @param  array<int, array<string, mixed>>  $objetos
     * @return array<int, array<string, mixed>>
     */
    private static function completar(array $objetos): array
    {
        $contadores = [];
        $base = base_convert((string) now()->getTimestampMs(), 10, 36);

        foreach ($objetos as $i => &$objeto) {
            $objeto = ['id' => 'o'.$base.base_convert((string) $i, 10, 36)] + $objeto;

            if (in_array($objeto['tipo'], ['marcador', 'anotacion'], true)) {
                $contadores[$objeto['tipo']] = ($contadores[$objeto['tipo']] ?? 0) + 1;
                $objeto['numero'] = $contadores[$objeto['tipo']];
            }

            if (isset($objeto['x'], $objeto['y'])) {
                $objeto['x'] = round($objeto['x'], 4);
                $objeto['y'] = round($objeto['y'], 4);
                $objeto['zona'] = Catalogo::zonaDe($objeto['x'], $objeto['y'])['id'] ?? null;
            }
        }

        return $objetos;
    }

    /**
     * Catmull-Rom: pasa por todos los puntos de control con una curva continua.
     *
     * @param  array<int, array<int, float>>  $puntos
     * @return array<int, array<int, float>>
     */
    private static function suavizar(array $puntos, int $pasos = 8): array
    {
        $n = count($puntos);
        if ($n < 3) {
            return array_map(fn ($p) => [round($p[0], 4), round($p[1], 4)], $puntos);
        }

        $salida = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $puntos[max(0, $i - 1)];
            $p1 = $puntos[$i];
            $p2 = $puntos[$i + 1];
            $p3 = $puntos[min($n - 1, $i + 2)];

            for ($k = 0; $k < $pasos; $k++) {
                $t = $k / $pasos;
                $t2 = $t * $t;
                $t3 = $t2 * $t;
                $punto = [];
                foreach ([0, 1] as $c) {
                    $punto[] = round(0.5 * (2 * $p1[$c] + (-$p0[$c] + $p2[$c]) * $t
                        + (2 * $p0[$c] - 5 * $p1[$c] + 4 * $p2[$c] - $p3[$c]) * $t2
                        + (-$p0[$c] + 3 * $p1[$c] - 3 * $p2[$c] + $p3[$c]) * $t3), 4);
                }
                $salida[] = $punto;
            }
        }
        $ultimo = $puntos[$n - 1];
        $salida[] = [round($ultimo[0], 4), round($ultimo[1], 4)];

        return $salida;
    }

    /*
    |--------------------------------------------------------------------------
    | PNG
    |--------------------------------------------------------------------------
    |
    | Reproduce lo que exporta el editor (exportarPng.js): la plantilla a
    | 1450 x 848 por dos, y encima los trazos con el grosor, el patrón y el
    | color del catálogo, los marcadores con su halo y su número, y las notas
    | con su globo numerado.
    |
    */

    /**
     * @param  array<int, array<string, mixed>>  $objetos
     */
    private static function dibujar(array $objetos): string
    {
        $plantilla = Catalogo::plantilla();
        $ancho = $plantilla['ancho'] * self::ESCALA;
        $alto = $plantilla['alto'] * self::ESCALA;

        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));

        $fondo = imagecreatefrompng(Catalogo::rutaPlantilla());
        imagecopyresampled($lienzo, $fondo, 0, 0, 0, 0, $ancho, $alto, imagesx($fondo), imagesy($fondo));
        imagedestroy($fondo);

        $u = self::ESCALA;
        $aPx = fn (array $p) => [$p[0] * $ancho, $p[1] * $alto];

        foreach ($objetos as $o) {
            if ($o['tipo'] !== 'trazo') {
                continue;
            }
            $estilo = Catalogo::trayecto($o['trayecto']) ?? [];
            $color = self::color($lienzo, Catalogo::hexColor($o['color']) ?? '#243757');
            $grosor = (float) ($o['grosor'] ?? 3) * $u;
            $puntos = array_map($aPx, $o['puntos']);

            if (($estilo['render'] ?? null) === 'ondulado') {
                $puntos = self::ondular($puntos, ($estilo['parametros']['amplitud'] ?? 3) * $u, ($estilo['parametros']['longitud'] ?? 9) * $u);
            }

            $patron = isset($estilo['patron']) ? array_map(fn ($v) => (float) $v * $u, explode(' ', $estilo['patron'])) : null;
            self::linea($lienzo, $puntos, $grosor, $color, $patron);
        }

        $fuente = self::fuente();
        $blanco = imagecolorallocatealpha($lienzo, 255, 255, 255, 28);
        $azulOscuro = self::color($lienzo, '#243757');

        foreach ($objetos as $o) {
            if ($o['tipo'] === 'marcador') {
                [$x, $y] = $aPx([$o['x'], $o['y']]);
                $lado = 26 * $u;
                $color = self::color($lienzo, Catalogo::hexColor($o['color']) ?? '#243757');
                imagefilledellipse($lienzo, (int) $x, (int) $y, (int) ($lado * 1.24), (int) ($lado * 1.24), $blanco);
                self::simbolo($lienzo, $o['marcador'], $x, $y, $lado / 24, $color);
                if ($fuente) {
                    self::texto($lienzo, (string) $o['numero'], $x + $lado * 0.62, $y - $lado * 0.42, 12 * $u, $color, $fuente);
                }
            }

            if ($o['tipo'] === 'anotacion') {
                [$x, $y] = $aPx([$o['x'], $o['y']]);
                $radio = 11 * $u;
                $cy = $y - 24 * $u;
                self::linea($lienzo, [[$x, $y], [$x, $cy]], 1.5 * $u, $azulOscuro);
                imagefilledellipse($lienzo, (int) $x, (int) $y, 6 * $u, 6 * $u, $azulOscuro);
                imagefilledellipse($lienzo, (int) $x, (int) $cy, (int) ($radio * 2 + 4 * $u), (int) ($radio * 2 + 4 * $u), self::color($lienzo, '#FFFFFF'));
                imagefilledellipse($lienzo, (int) $x, (int) $cy, (int) ($radio * 2), (int) ($radio * 2), $azulOscuro);
                if ($fuente) {
                    $caja = imagettfbbox(13 * $u * 0.75, 0, $fuente, (string) $o['numero']);
                    $anchoTexto = $caja[2] - $caja[0];
                    imagettftext($lienzo, 13 * $u * 0.75, 0, (int) ($x - $anchoTexto / 2), (int) ($cy + 4.5 * $u), self::color($lienzo, '#FFFFFF'), $fuente, (string) $o['numero']);
                }
            }
        }

        ob_start();
        imagepng($lienzo, null, 6);
        imagedestroy($lienzo);

        return (string) ob_get_clean();
    }

    /**
     * Polilínea gruesa con extremos redondeados: se estampa un círculo a cada
     * píxel del recorrido, que es lo que da un trazo liso con cualquier grosor.
     *
     * @param  array<int, array<int, float>>  $puntos
     * @param  array<int, float>|null  $patron  guiones y huecos, como stroke-dasharray
     */
    private static function linea(\GdImage $img, array $puntos, float $grosor, int $color, ?array $patron = null): void
    {
        $diametro = max(1, (int) round($grosor));
        $recorrido = 0.0;
        $ciclo = $patron ? array_sum($patron) : 0;

        for ($i = 0; $i < count($puntos) - 1; $i++) {
            [$x1, $y1] = $puntos[$i];
            [$x2, $y2] = $puntos[$i + 1];
            $largo = hypot($x2 - $x1, $y2 - $y1);
            $pasos = max(1, (int) ceil($largo));

            for ($k = 0; $k <= $pasos; $k++) {
                $s = $recorrido + $largo * $k / $pasos;
                if ($patron && fmod($s, $ciclo) > $patron[0]) {
                    continue;
                }
                $x = $x1 + ($x2 - $x1) * $k / $pasos;
                $y = $y1 + ($y2 - $y1) * $k / $pasos;
                imagefilledellipse($img, (int) round($x), (int) round($y), $diametro, $diametro, $color);
            }
            $recorrido += $largo;
        }
    }

    /**
     * El trazo epifascial: una onda senoidal a lo largo del recorrido, igual
     * que caminoOndulado() del editor.
     *
     * @param  array<int, array<int, float>>  $puntos
     * @return array<int, array<int, float>>
     */
    private static function ondular(array $puntos, float $amplitud, float $longitud): array
    {
        $paso = $longitud / 8;
        $salida = [];
        $s = 0.0;
        $siguiente = 0.0;

        for ($i = 0; $i < count($puntos) - 1; $i++) {
            [$x1, $y1] = $puntos[$i];
            [$x2, $y2] = $puntos[$i + 1];
            $largo = hypot($x2 - $x1, $y2 - $y1);
            if ($largo == 0.0) {
                continue;
            }
            $ux = ($x2 - $x1) / $largo;
            $uy = ($y2 - $y1) / $largo;

            while ($siguiente <= $s + $largo) {
                $t = $siguiente - $s;
                $f = sin(2 * M_PI * $siguiente / $longitud) * $amplitud;
                $salida[] = [$x1 + $ux * $t - $uy * $f, $y1 + $uy * $t + $ux * $f];
                $siguiente += $paso;
            }
            $s += $largo;
        }

        return count($salida) >= 4 ? $salida : $puntos;
    }

    private static function simbolo(\GdImage $img, string $marcador, float $x, float $y, float $k, int $color): void
    {
        $en = fn (float $px, float $py) => [$x + ($px - 12) * $k, $y + ($py - 12) * $k];

        match ($marcador) {
            'perforante' => self::anillo($img, $x, $y, 7 * $k, 2.4 * $k, $color),
            'golfo_venoso' => (function () use ($img, $en, $x, $y, $k, $color) {
                self::linea($img, [$en(0, 12), $en(24, 12)], 2.2 * $k, $color);
                imagefilledellipse($img, (int) $x, (int) $y, (int) (13 * $k), (int) (13 * $k), $color);
            })(),
            'safenectomia' => (function () use ($img, $en, $k, $color) {
                self::linea($img, [$en(1, 12), $en(23, 12)], 2 * $k, $color);
                foreach ([5, 10, 15, 20] as $vx) {
                    self::linea($img, [$en($vx, 4.5), $en($vx, 19.5)], 2 * $k, $color);
                }
            })(),
            default => self::anillo($img, $x, $y, 9 * $k, 2 * $k, $color),
        };
    }

    private static function anillo(\GdImage $img, float $x, float $y, float $radio, float $grosor, int $color): void
    {
        $puntos = [];
        for ($a = 0; $a <= 64; $a++) {
            $puntos[] = [$x + cos($a / 64 * 2 * M_PI) * $radio, $y + sin($a / 64 * 2 * M_PI) * $radio];
        }
        self::linea($img, $puntos, $grosor, $color);
    }

    private static function texto(\GdImage $img, string $texto, float $x, float $y, float $tamano, int $color, string $fuente): void
    {
        $puntos = $tamano * 0.75;
        $blanco = imagecolorallocate($img, 255, 255, 255);
        foreach ([[-2, 0], [2, 0], [0, -2], [0, 2], [-2, -2], [2, 2], [-2, 2], [2, -2]] as [$dx, $dy]) {
            imagettftext($img, $puntos, 0, (int) $x + $dx, (int) $y + $dy, $blanco, $fuente, $texto);
        }
        imagettftext($img, $puntos, 0, (int) $x, (int) $y, $color, $fuente, $texto);
    }

    private static function color(\GdImage $img, string $hex): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return imagecolorallocate($img, $r, $g, $b);
    }

    private static function fuente(): ?string
    {
        foreach (['DejaVuSans-Bold.ttf', 'DejaVuSansCondensed-Bold.ttf', 'DejaVuSans.ttf'] as $archivo) {
            $ruta = base_path('vendor/mpdf/mpdf/ttfonts/'.$archivo);
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
