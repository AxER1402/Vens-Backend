<?php

namespace App\Support\Reportes;

use App\Models\ClinicalHistory;

/**
 * Contenido del reporte de una historia clínica.
 *
 * Devuelve el documento ya resuelto —etiquetas legibles, valores formateados,
 * secciones vacías descartadas— para que el PDF y el Word se limiten a pintarlo.
 * Toda la lógica de "qué dice el informe" vive aquí; "cómo se ve" vive en los
 * generadores.
 *
 * El reporte cubre **una sola consulta**: no arrastra el historial del paciente
 * ni los estudios de otras fechas.
 */
class DatosHistoriaClinica
{
    public function __construct(private readonly ClinicalHistory $historia)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function construir(bool $incluirMapeo = true): array
    {
        return array_merge($this->cabecera(), [
            'secciones' => $this->secciones($incluirMapeo),
        ]);
    }

    /**
     * Envoltura del documento: título, ficha del paciente, datos de la consulta
     * y firma. Se separa de las secciones para que un informe compuesto pueda
     * quedarse con la cabecera de la consulta y añadirle otros anexos.
     *
     * @return array<string, mixed>
     */
    public function cabecera(): array
    {
        $this->historia->loadMissing(['patient', 'creator']);

        return [
            'titulo' => 'Historia Clínica',
            'archivo' => 'historia-clinica',
            'borrador' => $this->historia->estado_registro === 'Borrador',
            'paciente' => Ficha::paciente($this->historia->patient),
            'meta' => [
                'Fecha de consulta' => Formato::fecha($this->historia->fecha_consulta),
                'Expediente n.º' => (string) $this->historia->id,
                'Estado' => Formato::valor($this->historia->estado_registro),
            ],
            'secciones' => [],
            'firma' => Ficha::firma($this->historia->creator),
            'nombre_archivo_base' => $this->historia->patient?->nombre,
            'fecha_archivo' => $this->historia->fecha_consulta,
        ];
    }

    /**
     * Secciones clínicas de la consulta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function secciones(bool $incluirMapeo = true): array
    {
        $this->historia->loadMissing(['patient', 'options', 'creator']);

        return array_values(array_filter([
            $this->interrogatorio(),
            $this->antecedentes(),
            $this->ginecologicos(),
            $this->examenFisico(),
            $this->diagnostico(),
            $this->tratamiento(),
            $this->evolucion(),
            $incluirMapeo ? $this->mapeoVenoso() : null,
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones
    |--------------------------------------------------------------------------
    |
    | Cada una devuelve null cuando no tiene nada que decir. Un informe con
    | epígrafes vacíos se lee como un formulario a medio llenar.
    |
    */

    /**
     * @return array<string, mixed>|null
     */
    private function interrogatorio(): ?array
    {
        $campos = [
            'Consulta por' => Formato::valor($this->historia->consulta_por),
            'Zonas de la pierna' => Formato::lista($this->seleccion('zonas_pierna')),
            'Síntomas' => Formato::lista($this->seleccion('sintomas')),
            'Los síntomas aumentan con' => Formato::lista($this->seleccion('sintomas_aumentan')),
            'Los síntomas disminuyen con' => Formato::lista($this->seleccion('sintomas_disminuyen')),
            'Otros que los disminuyen' => Formato::valor($this->historia->disminuyen_otros),
        ];

        return Ficha::seccionCampos('Motivo de consulta e interrogatorio', $campos);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function antecedentes(): ?array
    {
        $campos = [
            'Familiares con várices' => Formato::booleano($this->historia->familiar_varices),
            'Alergias' => Formato::valor($this->historia->alergias),
            'Enfermedades' => Formato::lista($this->seleccion('enfermedades')),
            'Otras enfermedades' => Formato::valor($this->historia->enfermedades_otros),
            'Cirugías' => Formato::valor($this->historia->cirugias),
        ];

        return Ficha::seccionCampos('Antecedentes', $campos);
    }

    /**
     * Se omite entera cuando no hay ni un dato: no tiene sentido imprimirla en
     * el expediente de un paciente hombre.
     *
     * @return array<string, mixed>|null
     */
    private function ginecologicos(): ?array
    {
        $campos = [
            'Gestas' => Formato::entero($this->historia->gestas),
            'Abortos' => Formato::entero($this->historia->abortos),
            'Partos' => Formato::entero($this->historia->partos),
            'Cesáreas' => Formato::entero($this->historia->cesareas),
            'Hijos vivos' => Formato::entero($this->historia->hijos_vivos),
            'Hijos muertos' => Formato::entero($this->historia->hijos_muertos),
            'Última menstruación' => Formato::fecha($this->historia->ultima_menstruacion),
            'Hormonas' => Formato::valor($this->historia->hormonas),
        ];

        return Ficha::seccionCampos('Antecedentes ginecológicos', $campos);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function examenFisico(): ?array
    {
        $campos = [
            'Presión arterial' => Formato::presion($this->historia->presion_arterial),
            'Frecuencia cardíaca' => Formato::numero($this->historia->frecuencia_cardiaca, 'lpm'),
            'Frecuencia respiratoria' => Formato::numero($this->historia->frecuencia_respiratoria, 'rpm'),
            'Temperatura' => Formato::numero($this->historia->temperatura, '°C'),
            'Peso' => Formato::numero($this->historia->peso, 'kg'),
            // La perimetría es la medida con la que se sigue el edema de una
            // consulta a la siguiente, así que se imprime siempre junta.
            'Perímetro de tobillo' => Formato::numero($this->historia->perimetro_tobillo, 'cm'),
            'Perímetro de pantorrilla' => Formato::numero($this->historia->perimetro_pantorrilla, 'cm'),
            'Ubicación de la patología' => Formato::valor($this->historia->ubicacion_patologia),
        ];

        return Ficha::seccionCampos('Examen físico', $campos);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function diagnostico(): ?array
    {
        $campos = [
            'Clase clínica (CEAP · C)' => Formato::valor($this->historia->ceap_c),
            'Diagnóstico CEAP' => Formato::lista($this->seleccion('ceap_diagnostico')),
        ];

        return Ficha::seccionCampos('Diagnóstico CEAP', $campos);
    }

    /**
     * Plan de tratamiento. Las indicaciones marcadas se cruzan con lo que se
     * recetó en cada una: marcar "Venotónico" dice el tipo de tratamiento, y el
     * detalle dice cuál.
     *
     * @return array<string, mixed>|null
     */
    private function tratamiento(): ?array
    {
        $campos = [
            'Concentración' => Formato::numero($this->historia->esclero_concentracion, '%'),
            'Forma' => Formato::valor($this->historia->esclero_forma),
            'Volumen' => Formato::numero($this->historia->esclero_volumen, 'ml'),
            'Zonas tratadas' => Formato::lista($this->seleccion('tx_zonas')),
        ];

        $seccion = Ficha::seccionCampos('Plan de tratamiento y escleroterapia', $campos);

        $indicaciones = $this->indicaciones();

        if ($indicaciones === []) {
            return $seccion;
        }

        $tabla = [
            'titulo' => $seccion ? null : 'Indicaciones',
            'tipo' => 'tabla',
            'encabezados' => ['Indicación', 'Detalle'],
            'filas' => $indicaciones,
            'anchos' => [38, 62],
        ];

        // Cuando hay ambas cosas, la tabla se cuelga de la misma sección para que
        // el epígrafe no se repita.
        if ($seccion) {
            $seccion['extra'] = $tabla;

            return $seccion;
        }

        $tabla['titulo'] = 'Indicaciones';

        return $tabla;
    }

    /**
     * Filas «Indicación → qué se recetó».
     *
     * @return array<int, array<int, string>>
     */
    private function indicaciones(): array
    {
        $detalle = $this->historia->indicaciones_detalle ?? [];
        $filas = [];

        foreach ($this->opcionesDe('indicaciones') as $opcion) {
            // El detalle se indexa por el `valor` del catálogo, no por la etiqueta
            $filas[] = [
                Formato::valor($opcion->etiqueta ?? $opcion->valor),
                Formato::valor($detalle[$opcion->valor] ?? null),
            ];
        }

        if (Formato::hayDato($this->historia->indicaciones_otros)) {
            $filas[] = ['Otras', Formato::valor($this->historia->indicaciones_otros)];
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evolucion(): ?array
    {
        $campos = [
            'Evolución' => Formato::valor($this->historia->evolucion),
            'Estado general' => Formato::valor($this->historia->estado_general),
            'Observaciones' => Formato::lista($this->seleccion('observaciones')),
        ];

        $seccion = Ficha::seccionCampos('Evolución y observaciones', $campos);

        if (Formato::hayDato($this->historia->notas)) {
            $nota = ['tipo' => 'texto', 'titulo' => null, 'texto' => trim($this->historia->notas)];

            if ($seccion) {
                $seccion['extra'] = $nota;

                return $seccion;
            }

            return ['tipo' => 'texto', 'titulo' => 'Notas', 'texto' => trim($this->historia->notas)];
        }

        return $seccion;
    }

    /**
     * El mapeo venoso va como anexo de la consulta: vive en las mismas columnas
     * de la misma fila, así que forma parte de "los datos de esta consulta".
     *
     * @return array<string, mixed>|null
     */
    private function mapeoVenoso(): ?array
    {
        $mapeo = new DatosMapeoVenoso($this->historia);

        return $mapeo->anexo();
    }

    /*
    |--------------------------------------------------------------------------
    | Selecciones del catálogo
    |--------------------------------------------------------------------------
    */

    /**
     * Opciones marcadas de una categoría.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\ClinicalOption>
     */
    private function opcionesDe(string $categoria)
    {
        return $this->historia->options->where('categoria', $categoria)->sortBy('orden')->values();
    }

    /**
     * Etiquetas legibles de una categoría.
     *
     * Se leen de las opciones y no del accesor `selecciones` del modelo, que
     * devuelve el `valor` —un código— y no el texto que debe salir impreso.
     *
     * @return array<int, string>
     */
    private function seleccion(string $categoria): array
    {
        return $this->opcionesDe($categoria)
            ->map(fn ($opcion) => $opcion->etiqueta ?? $opcion->valor)
            ->all();
    }
}
