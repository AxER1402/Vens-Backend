<?php

namespace App\Support\Reportes;

use App\Models\ClinicalHistory;

/**
 * Informe de una consulta, armado con las partes que se pidan.
 *
 * El médico no siempre entrega lo mismo: a veces basta la consulta, a veces va
 * acompañada del Ecodöppler, y a veces quiere solo la lámina del mapeo. En vez
 * de tres descargas sueltas que el paciente tiene que juntar, esto compone **un
 * solo documento** con las partes elegidas.
 *
 * Cuando se pide una sola parte se delega en su constructor de siempre, de modo
 * que el informe suelto sale exactamente igual que antes —incluido el mapeo, que
 * en solitario se emite apaisado porque su lámina es horizontal—. Solo cuando
 * hay más de una parte se entra a componer, y entonces todo va en vertical: una
 * mezcla de orientaciones dentro del mismo archivo se imprime mal.
 */
class DatosInforme
{
    /**
     * Partes que puede llevar el informe, en el orden en que se imprimen.
     *
     * @var array<int, string>
     */
    public const PARTES = ['historia', 'mapeo', 'doppler'];

    public function __construct(private readonly ClinicalHistory $historia)
    {
    }

    /**
     * Partes que esta consulta puede ofrecer hoy: no tiene sentido dejar marcar
     * un anexo que no existe.
     *
     * @return array<int, string>
     */
    public function partesDisponibles(): array
    {
        $disponibles = ['historia'];

        if ((new DatosMapeoVenoso($this->historia))->tieneMapeo()) {
            $disponibles[] = 'mapeo';
        }

        if ($this->estudios()->isNotEmpty()) {
            $disponibles[] = 'doppler';
        }

        return $disponibles;
    }

    /**
     * Normalizar lo que llega por la petición contra lo que existe.
     *
     * @param  array<int, string>|null  $pedidas
     * @return array<int, string>
     */
    public function resolverPartes(?array $pedidas): array
    {
        $disponibles = $this->partesDisponibles();

        if ($pedidas === null) {
            return $disponibles;
        }

        // Se conserva el orden canónico, no el orden en que llegaron
        $partes = array_values(array_intersect(self::PARTES, $pedidas, $disponibles));

        // Pedir solo partes inexistentes no puede acabar en un PDF en blanco
        return $partes === [] ? ['historia'] : $partes;
    }

    /**
     * @param  array<int, string>  $partes
     * @return array<string, mixed>
     */
    public function construir(array $partes): array
    {
        if (count($partes) === 1) {
            return $this->documentoSuelto($partes[0]);
        }

        return $this->documentoCompuesto($partes);
    }

    /*
    |--------------------------------------------------------------------------
    | Una sola parte
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function documentoSuelto(string $parte): array
    {
        return match ($parte) {
            'mapeo' => (new DatosMapeoVenoso($this->historia))->construir(),
            'doppler' => (new DatosDoppler($this->estudios()->first()))->construir(),
            default => (new DatosHistoriaClinica($this->historia))->construir(false),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Varias partes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int, string>  $partes
     * @return array<string, mixed>
     */
    private function documentoCompuesto(array $partes): array
    {
        $constructor = new DatosHistoriaClinica($this->historia);
        $doc = $constructor->cabecera();

        $llevaHistoria = in_array('historia', $partes, true);
        $secciones = [];

        if ($llevaHistoria) {
            // El mapeo se añade después como anexo con su propio epígrafe, no
            // colgado del final de la historia
            $secciones = $constructor->secciones(false);
        }

        if (in_array('mapeo', $partes, true)) {
            $secciones = array_merge($secciones, $this->anexoMapeo($llevaHistoria || $secciones !== []));
        }

        if (in_array('doppler', $partes, true)) {
            $secciones = array_merge($secciones, $this->anexoDoppler($secciones !== []));
        }

        $doc['secciones'] = $secciones;
        $doc['titulo'] = $llevaHistoria ? 'Historia Clínica' : 'Informe Clínico';
        $doc['subtitulo'] = $this->subtitulo($partes);
        $doc['archivo'] = $llevaHistoria ? 'historia-clinica' : 'informe-clinico';

        return $doc;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function anexoMapeo(bool $conSalto): array
    {
        $anexo = (new DatosMapeoVenoso($this->historia))->anexo();

        if ($anexo === null) {
            return [];
        }

        return array_filter([
            $conSalto ? $this->portadaAnexo('Anexo · Mapeo venoso superficial') : null,
            ['tipo' => 'imagen', 'titulo' => null] + $anexo,
        ]);
    }

    /**
     * Un estudio por anexo: una consulta suele tener uno, pero nada impide que
     * lleve dos y el informe no debe perder ninguno.
     *
     * @return array<int, array<string, mixed>>
     */
    private function anexoDoppler(bool $conSalto): array
    {
        $secciones = [];

        foreach ($this->estudios() as $indice => $estudio) {
            $titulo = 'Anexo · Ecodöppler venoso del '.Formato::fecha($estudio->fecha_estudio);

            if ($conSalto || $indice > 0) {
                $secciones[] = $this->portadaAnexo($titulo);
            }

            foreach ((new DatosDoppler($estudio))->secciones() as $seccion) {
                $secciones[] = $seccion;
            }
        }

        return $secciones;
    }

    /**
     * Encabezado que abre un anexo en página nueva, para que se vea dónde
     * termina la consulta y empieza el estudio adjunto.
     *
     * @return array<string, mixed>
     */
    private function portadaAnexo(string $titulo): array
    {
        return ['tipo' => 'anexo', 'titulo' => $titulo, 'salto' => true];
    }

    /**
     * @param  array<int, string>  $partes
     */
    private function subtitulo(array $partes): string
    {
        $nombres = [
            'historia' => 'consulta',
            'mapeo' => 'mapeo venoso',
            'doppler' => 'Ecodöppler',
        ];

        $incluidas = array_map(fn ($p) => $nombres[$p], $partes);
        $ultima = array_pop($incluidas);

        return 'Incluye '.($incluidas === [] ? $ultima : implode(', ', $incluidas).' y '.$ultima);
    }

    /**
     * Estudios de Ecodöppler adjuntos a la consulta, del más antiguo al más
     * reciente y sin los dados de baja.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\DopplerReport>
     */
    private function estudios()
    {
        return $this->historia->dopplerReports()
            ->where('activo', true)
            ->orderBy('fecha_estudio')
            ->orderBy('id')
            ->get();
    }
}
