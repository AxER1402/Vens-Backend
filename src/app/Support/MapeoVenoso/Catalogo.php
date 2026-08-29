<?php

namespace App\Support\MapeoVenoso;

/**
 * Lectura del catálogo clínico del mapeo venoso (config/mapeo-venoso.php).
 *
 * Es la pieza que convierte lo que el editor archiva ('perforante',
 * 'izq_antero_interna') en lo que un humano lee ("Perforante insuficiente",
 * "MII · Cara antero-interna"). La usan la validación del documento vectorial y
 * el reporte en PDF, que es justamente lo que hace que ese reporte valga más que
 * exportar la imagen suelta.
 *
 * Los índices se arman una sola vez por proceso: el reporte de un mapeo con
 * decenas de objetos consulta el catálogo una vez por objeto.
 */
class Catalogo
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $coloresPorId = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $trayectosPorId = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $marcadoresPorId = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $hallazgosPorId = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $zonasPorId = null;

    /*
    |--------------------------------------------------------------------------
    | Plantilla
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    public static function plantilla(): array
    {
        return config('mapeo-venoso.plantilla');
    }

    public static function plantillaId(): string
    {
        return config('mapeo-venoso.plantilla.id');
    }

    /**
     * Ruta absoluta de la plantilla en el disco local.
     *
     * El PDF necesita el archivo, no una URL: pasarle una URL a mPDF provoca una
     * petición HTTP de ida y vuelta que además falla cuando APP_URL no resuelve
     * desde dentro del contenedor.
     */
    public static function rutaPlantilla(): ?string
    {
        $ruta = public_path(config('mapeo-venoso.plantilla.imagen'));

        return is_file($ruta) ? $ruta : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Colores: significado clínico del vaso
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function colores(): array
    {
        return config('mapeo-venoso.colores');
    }

    /**
     * @return array<int, string>
     */
    public static function idsColor(): array
    {
        return array_column(self::colores(), 'id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function color(?string $id): ?array
    {
        if ($id === null) {
            return null;
        }

        self::$coloresPorId ??= array_column(config('mapeo-venoso.colores'), null, 'id');

        return self::$coloresPorId[$id] ?? null;
    }

    /**
     * Nombre del color: "Rojo".
     */
    public static function etiquetaColor(?string $id): string
    {
        if ($id === null || $id === '') {
            return '—';
        }

        return self::color($id)['label'] ?? $id;
    }

    /**
     * Lo que el color significa: "Vena incompetente / reflujo patológico.".
     *
     * Es lo que se imprime en el informe. El nombre del color por sí solo no
     * dice nada en una hoja fotocopiada en blanco y negro.
     */
    public static function significadoColor(?string $id): string
    {
        return self::color($id)['ayuda'] ?? '—';
    }

    /**
     * Hexadecimal con el que se pinta, para el editor y la leyenda en pantalla.
     */
    public static function hexColor(?string $id): ?string
    {
        return self::color($id)['hex'] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Trayectos: patrón de línea de un recorrido venoso
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function trayectos(): array
    {
        return config('mapeo-venoso.trayectos');
    }

    /**
     * @return array<int, string>
     */
    public static function idsTrayecto(): array
    {
        return array_column(self::trayectos(), 'id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function trayecto(?string $id): ?array
    {
        if ($id === null) {
            return null;
        }

        self::$trayectosPorId ??= array_column(config('mapeo-venoso.trayectos'), null, 'id');

        return self::$trayectosPorId[$id] ?? null;
    }

    /**
     * Nombre legible de un trayecto. Un id fuera del catálogo se devuelve tal
     * cual: en un informe clínico es preferible imprimir un código crudo a
     * imprimir un hueco.
     */
    public static function etiquetaTrayecto(?string $id): string
    {
        if ($id === null || $id === '') {
            return '—';
        }

        return self::trayecto($id)['label'] ?? $id;
    }

    /*
    |--------------------------------------------------------------------------
    | Marcadores: hallazgos puntuales
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function marcadores(): array
    {
        return config('mapeo-venoso.marcadores');
    }

    /**
     * @return array<int, string>
     */
    public static function idsMarcador(): array
    {
        return array_column(self::marcadores(), 'id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function marcador(?string $id): ?array
    {
        if ($id === null) {
            return null;
        }

        self::$marcadoresPorId ??= array_column(config('mapeo-venoso.marcadores'), null, 'id');

        return self::$marcadoresPorId[$id] ?? null;
    }

    public static function etiquetaMarcador(?string $id): string
    {
        if ($id === null || $id === '') {
            return '—';
        }

        return self::marcador($id)['label'] ?? $id;
    }

    /*
    |--------------------------------------------------------------------------
    | Hallazgos heredados
    |--------------------------------------------------------------------------
    |
    | Vocabulario anterior a la separación en color + trayecto + marcador. El
    | editor ya no lo ofrece; esto solo existe para releer mapeos archivados.
    |
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function hallazgos(?string $tipo = null): array
    {
        $hallazgos = config('mapeo-venoso.hallazgos_legacy');

        if ($tipo === null) {
            return $hallazgos;
        }

        return array_values(array_filter($hallazgos, fn ($h) => $h['tipo'] === $tipo));
    }

    /**
     * Hallazgo heredado por id, o null si el objeto ya usa el vocabulario nuevo.
     *
     * @return array<string, mixed>|null
     */
    public static function hallazgo(?string $id): ?array
    {
        if ($id === null) {
            return null;
        }

        self::$hallazgosPorId ??= array_column(config('mapeo-venoso.hallazgos_legacy'), null, 'id');

        return self::$hallazgosPorId[$id] ?? null;
    }

    /**
     * Ids válidos, opcionalmente restringidos a un tipo de objeto.
     *
     * @return array<int, string>
     */
    public static function idsHallazgo(?string $tipo = null): array
    {
        return array_column(self::hallazgos($tipo), 'id');
    }

    /**
     * Nombre legible de un hallazgo heredado. Si el id no está en el catálogo se
     * devuelve tal cual en lugar de perderlo.
     */
    public static function etiquetaHallazgo(?string $id): string
    {
        if ($id === null || $id === '') {
            return '—';
        }

        return self::hallazgo($id)['label'] ?? $id;
    }

    /*
    |--------------------------------------------------------------------------
    | Zonas anatómicas
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function zonas(): array
    {
        return config('mapeo-venoso.zonas');
    }

    /**
     * @return array<int, string>
     */
    public static function idsZona(): array
    {
        return array_column(self::zonas(), 'id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function zona(?string $id): ?array
    {
        if ($id === null) {
            return null;
        }

        self::$zonasPorId ??= array_column(config('mapeo-venoso.zonas'), null, 'id');

        return self::$zonasPorId[$id] ?? null;
    }

    /**
     * Zona que contiene un punto normalizado, o null si cayó fuera de las vistas
     * (la franja que separa los dos paneles, la cabecera o el pie de la lámina).
     *
     * Se recalcula al generar el reporte en lugar de confiar en el campo `zona`
     * que archivó el editor: si un mapeo viejo se guardó sin ese campo, o con la
     * calibración anterior, el informe sigue saliendo bien.
     *
     * @return array<string, mixed>|null
     */
    public static function zonaDe(?float $x, ?float $y): ?array
    {
        if ($x === null || $y === null) {
            return null;
        }

        foreach (self::zonas() as $zona) {
            [$x0, $y0, $x1, $y1] = $zona['rect'];

            if ($x >= $x0 && $x < $x1 && $y >= $y0 && $y < $y1) {
                return $zona;
            }
        }

        return null;
    }

    /**
     * Etiqueta legible de una zona: "MII · Cara antero-interna".
     */
    public static function etiquetaZona(?array $zona): string
    {
        if ($zona === null) {
            return 'Sin zona';
        }

        $abrev = config("mapeo-venoso.miembros.{$zona['miembro']}.abrev", $zona['miembro']);

        return "{$abrev} · {$zona['cara']}";
    }

    /*
    |--------------------------------------------------------------------------
    | Miembros y límites
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function miembros(): array
    {
        return config('mapeo-venoso.miembros');
    }

    public static function abrevMiembro(?string $miembro): string
    {
        return config("mapeo-venoso.miembros.{$miembro}.abrev", '—');
    }

    public static function limite(string $clave): int|float
    {
        return config("mapeo-venoso.limites.{$clave}");
    }

    /**
     * @return array<int, float|int>
     */
    public static function grosores(): array
    {
        return config('mapeo-venoso.grosores');
    }

    /**
     * @return array<int, int>
     */
    public static function versiones(): array
    {
        return config('mapeo-venoso.versiones');
    }

    /**
     * Vaciar los índices memorizados. Solo lo necesitan los tests que alteran la
     * configuración en caliente.
     */
    public static function olvidarIndices(): void
    {
        self::$coloresPorId = null;
        self::$trayectosPorId = null;
        self::$marcadoresPorId = null;
        self::$hallazgosPorId = null;
        self::$zonasPorId = null;
    }
}
