<?php

namespace App\Support\Reportes;

use App\Models\ClinicalHistory;
use App\Support\MapeoVenoso\Catalogo;
use Illuminate\Support\Facades\Storage;

/**
 * Contenido del reporte del mapeo venoso.
 *
 * La lámina sola es una imagen: dice dónde están los hallazgos pero no qué son.
 * Lo que convierte este documento en un reporte es la tabla que acompaña al
 * dibujo, donde cada marca numerada se lee como texto —"3 · Perforante
 * insuficiente · MII · Cara antero-interna"— y se puede correlacionar con el
 * Ecodöppler. Esa tabla sale del documento vectorial, no del PNG.
 *
 * Por eso el reporte solo existe en PDF: es una lámina a escala con su leyenda,
 * y en Word la imagen se convierte en un objeto flotante que se desplaza al
 * primer retoque y arruina la proporción con la que se imprime.
 */
class DatosMapeoVenoso
{
    public function __construct(private readonly ClinicalHistory $historia)
    {
    }

    public function tieneMapeo(): bool
    {
        return $this->historia->mapeo_venoso_path !== null
            && $this->rutaImagen() !== null;
    }

    /**
     * Documento completo, para la descarga suelta del mapeo.
     *
     * @return array<string, mixed>
     */
    public function construir(): array
    {
        $this->historia->loadMissing(['patient', 'creator']);

        $secciones = array_filter([
            $this->lamina(),
            $this->leyenda(),
            $this->tablaMarcadores(),
            $this->tablaAnotaciones(),
            $this->resumen(),
        ]);

        return [
            'titulo' => 'Mapeo Venoso',
            'archivo' => 'mapeo-venoso',
            'orientacion' => 'apaisada',
            'borrador' => $this->historia->estado_registro === 'Borrador',
            'paciente' => Ficha::paciente($this->historia->patient),
            'meta' => [
                'Fecha de consulta' => Formato::fecha($this->historia->fecha_consulta),
                'Expediente n.º' => (string) $this->historia->id,
                'Mapeo actualizado' => Formato::fechaHora($this->historia->mapeo_venoso_updated_at),
            ],
            'secciones' => array_values($secciones),
            'firma' => Ficha::firma($this->historia->creator),
            'nombre_archivo_base' => $this->historia->patient?->nombre,
            'fecha_archivo' => $this->historia->fecha_consulta,
        ];
    }

    /**
     * Versión reducida para colgarla como anexo de la historia clínica: la
     * lámina y sus hallazgos, sin repetir la ficha del paciente ni la firma, que
     * el informe anfitrión ya trae.
     *
     * @return array<string, mixed>|null
     */
    public function anexo(): ?array
    {
        if (! $this->tieneMapeo()) {
            return null;
        }

        $bloques = array_values(array_filter([
            $this->leyenda(),
            $this->tablaMarcadores(),
            $this->tablaAnotaciones(),
        ]));

        return [
            'tipo' => 'imagen',
            'titulo' => 'Mapeo venoso',
            // No se fuerza un salto de página: la lámina lleva
            // `page-break-inside: avoid`, así que baja sola a la hoja siguiente
            // cuando no cabe entera. Forzarlo dejaba media página en blanco cada
            // vez que la consulta terminaba cerca del borde superior.
            'ruta' => $this->rutaImagen(),
            'pie' => 'Mapeo actualizado el '.Formato::fechaHora($this->historia->mapeo_venoso_updated_at),
            'bloques' => $bloques,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>|null
     */
    private function lamina(): ?array
    {
        $ruta = $this->rutaImagen();

        if ($ruta === null) {
            return null;
        }

        return [
            'tipo' => 'imagen',
            'titulo' => null,
            'ruta' => $ruta,
            'pie' => null,
        ];
    }

    /**
     * Leyenda de los hallazgos que aparecen en este mapeo, no el catálogo
     * entero: una leyenda con diez entradas de las que solo tres se usan obliga
     * a buscar y hace más difícil leer la lámina.
     *
     * @return array<string, mixed>|null
     */
    private function leyenda(): ?array
    {
        $usados = [];

        foreach ($this->objetos() as $objeto) {
            $id = $objeto['hallazgo'] ?? null;

            if (is_string($id) && ! isset($usados[$id])) {
                $usados[$id] = true;
            }
        }

        if ($usados === []) {
            return null;
        }

        $filas = [];

        foreach (Catalogo::hallazgos() as $hallazgo) {
            if (isset($usados[$hallazgo['id']])) {
                $filas[] = [
                    $hallazgo['abrev'],
                    $hallazgo['label'],
                    $hallazgo['tipo'] === 'trazo' ? 'Trazo' : 'Marcador',
                ];
            }
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Leyenda',
            'encabezados' => ['Abrev.', 'Hallazgo', 'Tipo'],
            'filas' => $filas,
            'anchos' => [12, 63, 25],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tablaMarcadores(): ?array
    {
        $filas = [];

        foreach ($this->objetosOrdenados('marcador') as $objeto) {
            $zona = $this->zonaDe($objeto);

            $filas[] = [
                Formato::valor($objeto['numero'] ?? null),
                Catalogo::etiquetaHallazgo($objeto['hallazgo'] ?? null),
                Catalogo::abrevMiembro($zona['miembro'] ?? null),
                $zona['cara'] ?? 'Sin zona',
            ];
        }

        if ($filas === []) {
            return null;
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Hallazgos marcados',
            'encabezados' => ['N.º', 'Hallazgo', 'Miembro', 'Cara'],
            'filas' => $filas,
            'anchos' => [8, 47, 15, 30],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tablaAnotaciones(): ?array
    {
        $filas = [];

        foreach ($this->objetosOrdenados('anotacion') as $objeto) {
            $zona = $this->zonaDe($objeto);

            $filas[] = [
                Formato::valor($objeto['numero'] ?? null),
                Formato::valor($objeto['texto'] ?? null),
                Catalogo::abrevMiembro($zona['miembro'] ?? null),
                $zona['cara'] ?? 'Sin zona',
            ];
        }

        if ($filas === []) {
            return null;
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Anotaciones',
            'encabezados' => ['N.º', 'Anotación', 'Miembro', 'Cara'],
            'filas' => $filas,
            'anchos' => [8, 47, 15, 30],
        ];
    }

    /**
     * Cuántos hallazgos cayeron en cada miembro. Es el dato que el médico mira
     * primero cuando compara dos consultas.
     *
     * @return array<string, mixed>|null
     */
    private function resumen(): ?array
    {
        $cuenta = ['izq' => 0, 'der' => 0, 'sin' => 0];

        foreach ($this->objetos() as $objeto) {
            // Las etiquetas de texto son rótulos del dibujo, no hallazgos
            if (($objeto['tipo'] ?? null) === 'texto') {
                continue;
            }

            $zona = $this->zonaDe($objeto);

            if ($zona !== null) {
                $cuenta[$zona['miembro']]++;
            } else {
                $cuenta['sin']++;
            }
        }

        if ($cuenta['izq'] + $cuenta['der'] + $cuenta['sin'] === 0) {
            return null;
        }

        $campos = [
            Catalogo::abrevMiembro('der').' (derecho)' => (string) $cuenta['der'],
            Catalogo::abrevMiembro('izq').' (izquierdo)' => (string) $cuenta['izq'],
        ];

        if ($cuenta['sin'] > 0) {
            $campos['Fuera de las vistas'] = (string) $cuenta['sin'];
        }

        return [
            'tipo' => 'campos',
            'titulo' => 'Hallazgos por miembro',
            'campos' => $campos,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lectura del documento vectorial
    |--------------------------------------------------------------------------
    */

    /**
     * Objetos archivados, tolerando un documento ausente o malformado: un mapeo
     * anterior a esta función solo tiene el PNG, y aun así debe poder imprimirse.
     *
     * @return array<int, array<string, mixed>>
     */
    private function objetos(): array
    {
        $datos = $this->historia->mapeo_venoso_datos;

        if (! is_array($datos) || ! isset($datos['objetos']) || ! is_array($datos['objetos'])) {
            return [];
        }

        return array_values(array_filter($datos['objetos'], 'is_array'));
    }

    /**
     * Objetos de un tipo, por su número correlativo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function objetosOrdenados(string $tipo): array
    {
        $objetos = array_values(array_filter(
            $this->objetos(),
            fn ($o) => ($o['tipo'] ?? null) === $tipo
        ));

        usort($objetos, fn ($a, $b) => ($a['numero'] ?? 0) <=> ($b['numero'] ?? 0));

        return $objetos;
    }

    /**
     * Zona de un objeto.
     *
     * Se recalcula a partir de las coordenadas en lugar de confiar en el campo
     * `zona` que archivó el editor: así un mapeo guardado antes de que existiera
     * ese campo sigue saliendo bien clasificado en el informe.
     *
     * @param  array<string, mixed>  $objeto
     * @return array<string, mixed>|null
     */
    private function zonaDe(array $objeto): ?array
    {
        if (($objeto['tipo'] ?? null) === 'trazo') {
            $primero = $objeto['puntos'][0] ?? null;

            return is_array($primero)
                ? Catalogo::zonaDe((float) ($primero[0] ?? 0), (float) ($primero[1] ?? 0))
                : null;
        }

        if (! isset($objeto['x'], $objeto['y'])) {
            return null;
        }

        return Catalogo::zonaDe((float) $objeto['x'], (float) $objeto['y']);
    }

    /**
     * Ruta absoluta del PNG en el disco.
     *
     * El PDF necesita el archivo, no la URL pública: pasarle una URL a mPDF
     * provoca una petición HTTP de ida y vuelta que además falla cuando APP_URL
     * no resuelve desde dentro del contenedor.
     */
    private function rutaImagen(): ?string
    {
        $ruta = $this->historia->mapeo_venoso_path;

        if (! $ruta) {
            return null;
        }

        $disco = Storage::disk('public');

        return $disco->exists($ruta) ? $disco->path($ruta) : null;
    }
}
