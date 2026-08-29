<?php

namespace App\Support\Reportes;

use App\Models\DopplerReport;

/**
 * Contenido del reporte de Ecodöppler venoso de miembros inferiores.
 *
 * Un estudio informa los dos miembros con los mismos hallazgos, así que el
 * documento repite el mismo bloque para cada lado: der_ (MID) e izq_ (MII).
 *
 * Los dos lados van apilados y no en columnas paralelas. Una tabla de seis
 * columnas por lado puesta a media página se parte al llegar al borde y deja
 * medidas huérfanas en la página siguiente, que en un informe de medidas es
 * justo lo que no puede pasar.
 */
class DatosDoppler
{
    public function __construct(private readonly DopplerReport $estudio)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function construir(): array
    {
        $this->estudio->loadMissing(['patient', 'creator']);

        return array_merge($this->envoltura(), ['secciones' => $this->secciones()]);
    }

    /**
     * Hallazgos del estudio, sin la envoltura del documento, para poder
     * adjuntarlos a un informe compuesto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function secciones(): array
    {
        $secciones = [];

        foreach (DopplerReport::LADOS as $lado) {
            foreach ($this->bloqueDeLado($lado) as $seccion) {
                $secciones[] = $seccion;
            }
        }

        if (Formato::hayDato($this->estudio->conclusion)) {
            $secciones[] = [
                'tipo' => 'texto',
                'titulo' => 'Conclusión',
                'texto' => trim($this->estudio->conclusion),
            ];
        }

        return $secciones;
    }

    /**
     * @return array<string, mixed>
     */
    private function envoltura(): array
    {
        return [
            'titulo' => 'Reporte de Ecodöppler Venoso',
            'subtitulo' => 'Miembros inferiores',
            'archivo' => 'ecodoppler',
            'borrador' => $this->estudio->estado_registro === 'Borrador',
            'paciente' => Ficha::paciente($this->estudio->patient),
            'meta' => [
                'Fecha del estudio' => Formato::fecha($this->estudio->fecha_estudio),
                'Estudio n.º' => (string) $this->estudio->id,
                'Estado' => Formato::valor($this->estudio->estado_registro),
            ],
            'secciones' => [],
            'firma' => Ficha::firma($this->estudio->creator),
            'nombre_archivo_base' => $this->estudio->patient?->nombre,
            'fecha_archivo' => $this->estudio->fecha_estudio,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Un miembro
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bloqueDeLado(string $lado): array
    {
        $abrev = $lado === 'der' ? 'MID' : 'MII';
        $nombre = $lado === 'der' ? 'Miembro inferior derecho' : 'Miembro inferior izquierdo';
        $titulo = "{$nombre} ({$abrev})";

        $bloques = [];

        $campos = [
            'Sistema venoso profundo' => Formato::valor($this->estudio->{"{$lado}_profundo"}),
            'Perforantes' => Formato::valor($this->estudio->{"{$lado}_perforantes"}),
            'Trombosis' => Formato::valor($this->estudio->{"{$lado}_trombosis"}),
        ];

        $tabla = $this->tablaSegmentos($lado);

        // Si el lado no se informó, se dice explícitamente en vez de omitirlo:
        // en un estudio bilateral, un miembro ausente del informe se lee como un
        // descuido y no como "no se evaluó".
        if ($campos === array_fill_keys(array_keys($campos), Formato::VACIO) && $tabla === null) {
            return [[
                'tipo' => 'texto',
                'titulo' => $titulo,
                'texto' => 'No se registraron hallazgos para este miembro.',
            ]];
        }

        $seccion = Ficha::seccionCampos($titulo, $campos);

        if ($seccion) {
            $bloques[] = $seccion;
        }

        if ($tabla !== null) {
            if ($bloques === []) {
                $tabla['titulo'] = $titulo;
                $bloques[] = $tabla;
            } else {
                $bloques[0]['extra'] = $tabla;
            }
        }

        return $bloques;
    }

    /**
     * Tabla del sistema venoso superficial.
     *
     * El formulario manda siempre las cinco posiciones —las tres fijas (SFJ,
     * GSV Muslo, GSV Pierna) más dos que el médico nombra—, con las últimas
     * vacías si no las usó. Las vacías se saltan: una fila en blanco en una tabla
     * de medidas se lee como una medición que dio cero.
     *
     * @return array<string, mixed>|null
     */
    private function tablaSegmentos(string $lado): ?array
    {
        $segmentos = $this->estudio->{"{$lado}_segmentos"};

        if (! is_array($segmentos)) {
            return null;
        }

        $filas = [];

        foreach (array_values($segmentos) as $posicion => $segmento) {
            if (! is_array($segmento)) {
                continue;
            }

            // Los tres primeros llevan nombre fijo aunque el cliente no lo mande
            $nombre = $segmento['nombre'] ?? (DopplerReport::SEGMENTOS_FIJOS[$posicion] ?? null);

            $medidas = [
                $segmento['diametro_max'] ?? null,
                $segmento['velocidad'] ?? null,
                $segmento['duracion'] ?? null,
                $segmento['diametro'] ?? null,
            ];

            $observaciones = $segmento['observaciones'] ?? null;

            // Un segmento sin nombre y sin una sola medida no se informó
            if (! Formato::hayDato($nombre) && ! Formato::hayDato($medidas) && ! Formato::hayDato($observaciones)) {
                continue;
            }

            $filas[] = [
                Formato::valor($nombre),
                Formato::numero($medidas[0]),
                Formato::numero($medidas[1]),
                Formato::numero($medidas[2]),
                Formato::numero($medidas[3]),
                Formato::valor($observaciones),
            ];
        }

        if ($filas === []) {
            return null;
        }

        return [
            'tipo' => 'tabla',
            'titulo' => null,
            'encabezados' => ['Segmento', 'Ø Máx (mm)', 'Velocidad (cm/s)', 'Reflujo (s)', 'Ø (mm)', 'Observaciones'],
            'filas' => $filas,
            'anchos' => [20, 12, 14, 11, 10, 33],
        ];
    }
}
