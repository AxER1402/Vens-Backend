<?php

namespace Tests\Feature;

use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicalHistoryManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PNG de 1x1 px usado para probar el guardado del mapeo venoso.
     */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function paciente(): Patient
    {
        return Patient::create([
            'nombre' => 'María Elena Portillo',
            'edad' => 42,
            'telefono' => '+50378451200',
            'lugar_residencia' => 'Santa Ana',
            'estado_civil' => 'Casado/a',
            'religion' => 'Católica',
            'estado' => 'Activo',
        ]);
    }

    private function medico(): User
    {
        return User::where('rol', 'medico')->first();
    }

    public function test_doctor_can_register_a_complete_clinical_history(): void
    {
        $patient = $this->paciente();

        $payload = [
            'patient_id' => $patient->id,
            'estado_registro' => 'Finalizada',
            'consulta_por' => 'Enfermedad',
            'familiar_varices' => true,
            'alergias' => 'Penicilina',
            'presion_arterial' => '120/80',
            'frecuencia_cardiaca' => 78,
            'frecuencia_respiratoria' => 18,
            'temperatura' => 36.6,
            'peso' => 68.5,
            'perimetro_tobillo' => 24.5,
            'perimetro_pantorrilla' => 36.0,
            'ubicacion_patologia' => 'BILATERAL',
            'ceap_c' => 'C2a',
            'esclero_concentracion' => 1.0,
            'esclero_forma' => 'Espuma',
            'esclero_volumen' => 2.5,
            'indicaciones_detalle' => ['Venotónico' => 'Perivasc 950/50'],
            'evolucion' => 'Mejoría',
            'estado_general' => 'Requiere nuevas sesiones',
            'notas' => 'Paciente tolera bien el procedimiento.',
            'selecciones' => [
                'sintomas' => ['Calambres', 'Pesadez'],
                'ceap_diagnostico' => ['Primaria', 'Superficial'],
                'tx_zonas' => ['Telangiectasias'],
                'indicaciones' => ['Venotónico'],
            ],
        ];

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'patient_id' => $patient->id,
                    'estado_registro' => 'Finalizada',
                    'consulta_por' => 'Enfermedad',
                    'ceap_c' => 'C2a',
                    'selecciones' => [
                        'sintomas' => ['Calambres', 'Pesadez'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'tx_zonas' => ['Telangiectasias'],
                        'indicaciones' => ['Venotónico'],
                        'enfermedades' => [],
                    ],
                    'indicaciones_detalle' => ['Venotónico' => 'Perivasc 950/50'],
                ],
            ]);

        $this->assertDatabaseHas('clinical_histories', [
            'patient_id' => $patient->id,
            'estado_registro' => 'Finalizada',
            'ceap_c' => 'C2a',
            'perimetro_tobillo' => 24.5,
            'perimetro_pantorrilla' => 36.0,
            'created_by' => $this->medico()->id,
            'updated_by' => $this->medico()->id,
        ]);

        // Las cuatro listas marcadas deben quedar registradas en la tabla pivote
        $this->assertCount(6, ClinicalHistory::first()->options);

        // El medicamento concreto viaja aparte de la casilla marcada
        $this->assertSame(
            ['Venotónico' => 'Perivasc 950/50'],
            ClinicalHistory::first()->indicaciones_detalle
        );
    }

    public function test_draft_can_be_saved_with_only_the_patient(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => ['estado_registro' => 'Borrador'],
            ]);

        $this->assertDatabaseHas('clinical_histories', [
            'patient_id' => $patient->id,
            'estado_registro' => 'Borrador',
        ]);
    }

    public function test_clinical_history_cannot_be_saved_without_a_patient(): void
    {
        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'estado_registro' => 'Borrador',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    public function test_finalizing_requires_the_minimum_clinical_data(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Finalizada',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'consulta_por',
                'presion_arterial',
                'estado_general',
                'selecciones.ceap_diagnostico',
            ]);
    }

    public function test_vital_signs_are_validated_as_numbers_within_clinical_ranges(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
                'presion_arterial' => '120-80',
                'frecuencia_cardiaca' => 'ochenta',
                'frecuencia_respiratoria' => 500,
                'temperatura' => 80,
                'peso' => -5,
                'perimetro_tobillo' => 0.5,
                'perimetro_pantorrilla' => 'treinta',
                'ultima_menstruacion' => now()->addYear()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'presion_arterial',
                'frecuencia_cardiaca',
                'frecuencia_respiratoria',
                'temperatura',
                'peso',
                'perimetro_tobillo',
                'perimetro_pantorrilla',
                'ultima_menstruacion',
            ]);
    }

    public function test_selected_values_must_belong_to_the_clinical_catalog(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
                'selecciones' => [
                    'sintomas' => ['Calambres', 'Sintoma inventado'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selecciones.sintomas');
    }

    public function test_indicacion_detail_must_reference_a_catalog_indication(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
                'indicaciones_detalle' => ['Indicación inventada' => 'Algo'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('indicaciones_detalle');
    }

    public function test_ceap_c_only_accepts_letters_and_numbers(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
                'ceap_c' => 'C2-a',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ceap_c');
    }

    public function test_receptionist_cannot_register_a_clinical_history(): void
    {
        $patient = $this->paciente();
        $recepcionista = User::where('rol', 'recepcionista')->first();

        $response = $this->actingAs($recepcionista, 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('clinical_histories', 0);
    }

    public function test_doctor_can_update_a_clinical_history_and_replace_its_selections(): void
    {
        $patient = $this->paciente();

        $creada = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
                'selecciones' => ['sintomas' => ['Calambres', 'Pesadez']],
            ])->json('data.id');

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->putJson("/api/v1/clinical-histories/{$creada}", [
                'estado_registro' => 'Borrador',
                'notas' => 'Se ajusta el plan de tratamiento.',
                'selecciones' => ['sintomas' => ['Hinchazón']],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'notas' => 'Se ajusta el plan de tratamiento.',
                    'selecciones' => ['sintomas' => ['Hinchazón']],
                ],
            ]);
    }

    public function test_clinical_histories_can_be_listed_by_patient(): void
    {
        $patient = $this->paciente();

        $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
            ])->assertStatus(201);

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->getJson("/api/v1/patients/{$patient->id}/clinical-histories");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_id', $patient->id);
    }

    public function test_venous_map_is_stored_as_a_png_file(): void
    {
        Storage::fake('public');
        $patient = $this->paciente();

        $historia = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
            ])->json('data.id');

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson("/api/v1/clinical-histories/{$historia}/venous-map", [
                'imagen' => 'data:image/png;base64,' . self::PNG_BASE64,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $ruta = ClinicalHistory::find($historia)->mapeo_venoso_path;
        $this->assertNotNull($ruta);
        Storage::disk('public')->assertExists($ruta);
    }

    public function test_venous_map_rejects_content_that_is_not_a_png(): void
    {
        Storage::fake('public');
        $patient = $this->paciente();

        $historia = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
            ])->json('data.id');

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson("/api/v1/clinical-histories/{$historia}/venous-map", [
                'imagen' => 'data:image/png;base64,' . base64_encode('esto no es una imagen'),
            ]);

        $response->assertStatus(422);
        $this->assertNull(ClinicalHistory::find($historia)->mapeo_venoso_path);
    }

    /*
    |--------------------------------------------------------------------------
    | Documento vectorial del mapeo venoso
    |--------------------------------------------------------------------------
    |
    | El PNG es lo que se imprime; el documento vectorial es lo que permite
    | reabrir el mapeo y seguir editándolo en la consulta siguiente. Perderlo no
    | rompe nada visible hasta que el médico intenta continuar un mapeo y se
    | encuentra el lienzo en blanco, así que conviene probarlo a conciencia.
    |
    */

    /**
     * Crear una historia en borrador y devolver su id.
     */
    private function historiaEnBorrador(): int
    {
        return $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/clinical-histories', [
                'patient_id' => $this->paciente()->id,
                'estado_registro' => 'Borrador',
            ])->json('data.id');
    }

    /**
     * Documento vectorial mínimo pero completo, con un objeto de cada tipo.
     *
     * Los trazos y los marcadores usan el vocabulario de tres ejes: el color da
     * la lectura clínica del vaso y el trayecto o el marcador dicen qué se
     * dibujó.
     *
     * @param  array<int, array<string, mixed>>|null  $objetos
     * @return array<string, mixed>
     */
    private function documento(?array $objetos = null): array
    {
        return [
            'version' => 1,
            'plantilla' => 'merit-mmii-6-vistas',
            'objetos' => $objetos ?? [
                ['tipo' => 'trazo', 'color' => 'rojo', 'trayecto' => 'epifascial', 'puntos' => [[0.2, 0.3], [0.22, 0.45]]],
                ['tipo' => 'marcador', 'color' => 'azul', 'marcador' => 'perforante', 'x' => 0.25, 'y' => 0.5, 'numero' => 1],
                ['tipo' => 'anotacion', 'texto' => 'Reflujo al Valsalva', 'x' => 0.3, 'y' => 0.6, 'numero' => 1],
                ['tipo' => 'texto', 'texto' => 'Control 6 semanas', 'x' => 0.7, 'y' => 0.2, 'tamano' => 16],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $objetos
     */
    private function guardarMapeo(int $historia, ?array $objetos = null, bool $conDatos = true): \Illuminate\Testing\TestResponse
    {
        $carga = ['imagen' => 'data:image/png;base64,' . self::PNG_BASE64];

        if ($conDatos) {
            $carga['datos'] = $this->documento($objetos);
        }

        return $this->actingAs($this->medico(), 'sanctum')
            ->postJson("/api/v1/clinical-histories/{$historia}/venous-map", $carga);
    }

    public function test_venous_map_stores_and_returns_the_vector_document(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia)->assertStatus(200);

        $datos = ClinicalHistory::find($historia)->mapeo_venoso_datos;

        $this->assertSame(1, $datos['version']);
        $this->assertSame('merit-mmii-6-vistas', $datos['plantilla']);
        $this->assertCount(4, $datos['objetos']);
        $this->assertSame('epifascial', $datos['objetos'][0]['trayecto']);
        $this->assertSame('rojo', $datos['objetos'][0]['color']);
        $this->assertSame('perforante', $datos['objetos'][1]['marcador']);
        $this->assertNotNull(ClinicalHistory::find($historia)->mapeo_venoso_updated_at);
    }

    /**
     * Regresión: guardar solo la imagen no debe borrar el mapeo editable.
     *
     * Antes, `mapeo_venoso_datos` se asignaba siempre desde la petición, así que
     * un cliente que enviara únicamente el PNG dejaba la columna en NULL y
     * destruía el trabajo previo del médico sin ningún aviso.
     */
    public function test_saving_only_the_image_preserves_the_stored_vector_document(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia)->assertStatus(200);
        $this->assertCount(4, ClinicalHistory::find($historia)->mapeo_venoso_datos['objetos']);

        $this->guardarMapeo($historia, conDatos: false)->assertStatus(200);

        $datos = ClinicalHistory::find($historia)->mapeo_venoso_datos;
        $this->assertNotNull($datos, 'El documento vectorial se perdió al guardar solo la imagen.');
        $this->assertCount(4, $datos['objetos']);
    }

    public function test_venous_map_rejects_coordinates_outside_the_canvas(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'marcador', 'color' => 'azul', 'marcador' => 'perforante', 'x' => 1.4, 'y' => 0.5],
        ])->assertStatus(422)->assertJsonValidationErrors('datos.objetos.0.x');

        $this->assertNull(ClinicalHistory::find($historia)->mapeo_venoso_datos);
    }

    public function test_venous_map_rejects_a_marker_outside_the_catalog(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'marcador', 'color' => 'azul', 'marcador' => 'marcador_inventado', 'x' => 0.2, 'y' => 0.5],
        ])->assertStatus(422)->assertJsonValidationErrors('datos.objetos.0.marcador');
    }

    public function test_venous_map_rejects_a_route_outside_the_catalog(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'trazo', 'color' => 'rojo', 'trayecto' => 'trayecto_inventado', 'puntos' => [[0.2, 0.3], [0.3, 0.4]]],
        ])->assertStatus(422)->assertJsonValidationErrors('datos.objetos.0.trayecto');
    }

    /**
     * Los dos troncos safenos son un tipo de recorrido más, no un eje aparte:
     * se eligen en la misma lista que el resto de trayectos.
     */
    public function test_venous_map_accepts_a_named_saphenous_trunk_as_a_route(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'trazo', 'color' => 'rojo', 'trayecto' => 'safena_interna', 'puntos' => [[0.2, 0.3], [0.3, 0.4]]],
            ['tipo' => 'trazo', 'color' => 'auto', 'trayecto' => 'safena_externa', 'puntos' => [[0.9, 0.3], [0.92, 0.4]]],
        ])->assertStatus(200);

        $objetos = ClinicalHistory::find($historia)->mapeo_venoso_datos['objetos'];
        $this->assertSame('safena_interna', $objetos[0]['trayecto']);
        $this->assertSame('safena_externa', $objetos[1]['trayecto']);
    }

    /**
     * El color no es un gusto: es la lectura clínica del vaso. Un recorrido sin
     * color no dice si la vena es competente, refluyente o trombosada, así que
     * la lámina se podría dibujar pero el informe no se podría redactar.
     */
    public function test_venous_map_rejects_a_stroke_without_a_colour(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'trazo', 'trayecto' => 'subfascial', 'puntos' => [[0.2, 0.3], [0.3, 0.4]]],
        ])->assertStatus(422)->assertJsonValidationErrors('datos.objetos.0.color');
    }

    public function test_venous_map_rejects_a_colour_outside_the_catalog(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'trazo', 'color' => 'turquesa', 'trayecto' => 'subfascial', 'puntos' => [[0.2, 0.3], [0.3, 0.4]]],
        ])->assertStatus(422)->assertJsonValidationErrors('datos.objetos.0.color');
    }

    /**
     * Al reabrir un mapeo archivado el editor devuelve sus objetos tal y como
     * los leyó, con el vocabulario anterior a la separación en color, trayecto y
     * marcador. Si el backend lo rechazara, guardar una corrección sobre un
     * mapeo viejo fallaría entero y el médico perdería el trabajo.
     */
    public function test_venous_map_still_accepts_the_legacy_finding_vocabulary(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'trazo', 'hallazgo' => 'safena_interna', 'color' => '#0C7D8C', 'puntos' => [[0.2, 0.3], [0.22, 0.45]]],
            ['tipo' => 'marcador', 'hallazgo' => 'perforante', 'x' => 0.25, 'y' => 0.5, 'numero' => 1],
        ])->assertStatus(200);

        $this->assertCount(2, ClinicalHistory::find($historia)->mapeo_venoso_datos['objetos']);
    }

    /**
     * Con el vocabulario heredado, un marcador no puede llevar un hallazgo
     * pensado para trazos: el catálogo distingue los dos tipos y el reporte los
     * lee por separado.
     */
    public function test_venous_map_rejects_a_legacy_finding_of_the_wrong_kind(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'marcador', 'hallazgo' => 'safena_interna', 'x' => 0.2, 'y' => 0.5],
        ])->assertStatus(422)->assertJsonValidationErrors('datos.objetos.0.marcador');
    }

    public function test_venous_map_rejects_a_stroke_with_a_single_point(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'trazo', 'color' => 'rojo', 'trayecto' => 'subfascial', 'puntos' => [[0.2, 0.3]]],
        ])->assertStatus(422)->assertJsonValidationErrors('datos.objetos.0.puntos');
    }

    public function test_venous_map_rejects_an_empty_annotation(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $this->guardarMapeo($historia, [
            ['tipo' => 'anotacion', 'texto' => '   ', 'x' => 0.2, 'y' => 0.5],
        ])->assertStatus(422)->assertJsonValidationErrors('datos.objetos.0.texto');
    }

    /**
     * El tope de puntos es el que realmente acota el tamaño de la columna JSON:
     * limitar solo el número de objetos no serviría de nada porque un único
     * trazo puede traer cientos de miles de puntos.
     */
    public function test_venous_map_rejects_a_document_over_the_total_point_budget(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $puntos = array_fill(0, 4000, [0.5, 0.5]);
        $trazo = fn () => ['tipo' => 'trazo', 'color' => 'rojo', 'trayecto' => 'subfascial', 'puntos' => $puntos];

        $this->guardarMapeo($historia, array_fill(0, 6, $trazo()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('datos.objetos');
    }

    public function test_venous_map_rejects_an_unknown_template(): void
    {
        Storage::fake('public');
        $historia = $this->historiaEnBorrador();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson("/api/v1/clinical-histories/{$historia}/venous-map", [
                'imagen' => 'data:image/png;base64,' . self::PNG_BASE64,
                'datos' => ['version' => 1, 'plantilla' => 'otra-plantilla', 'objetos' => []],
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('datos.plantilla');
    }

    public function test_venous_map_catalog_is_available_to_authenticated_staff(): void
    {
        $response = $this->actingAs($this->medico(), 'sanctum')
            ->getJson('/api/v1/venous-map/catalog');

        $response->assertStatus(200)
            ->assertJsonPath('data.plantilla.id', 'merit-mmii-6-vistas')
            ->assertJsonCount(6, 'data.zonas')
            // Los seis de la lámina más el «Auto» con el que abre el editor
            ->assertJsonCount(7, 'data.colores')
            // Los seis patrones de la lámina más los dos troncos safenos
            ->assertJsonCount(8, 'data.trayectos')
            ->assertJsonCount(5, 'data.marcadores')
            // El vocabulario heredado no se publica: el editor no debe volver a
            // ofrecerlo.
            ->assertJsonMissingPath('data.hallazgos');
    }

    /**
     * Los `parametros` de un trayecto viajan siempre como objeto.
     *
     * Un array vacío de PHP se serializa como `[]`, así que los trayectos sin
     * parámetros llegarían al editor con una lista donde el resto trae un
     * diccionario. Es la incoherencia que rompe en el cliente meses después.
     */
    public function test_venous_map_catalog_serialises_route_parameters_as_objects(): void
    {
        $respuesta = $this->actingAs($this->medico(), 'sanctum')
            ->getJson('/api/v1/venous-map/catalog');

        $porId = array_column($respuesta->json('data.trayectos'), null, 'id');
        $this->assertSame(['amplitud' => 3, 'longitud' => 9], $porId['epifascial']['parametros']);

        // Sobre el cuerpo sin decodificar: al pasar por json() un objeto vacío
        // vuelve a ser un array de PHP y la comprobación se perdería.
        $this->assertStringContainsString(
            '"parametros":{}',
            $respuesta->getContent(),
            'Un trayecto sin parámetros se serializó como lista en vez de como objeto.'
        );
        $this->assertStringNotContainsString('"parametros":[]', $respuesta->getContent());
    }

    public function test_venous_map_catalog_requires_authentication(): void
    {
        $this->getJson('/api/v1/venous-map/catalog')->assertStatus(401);
    }
}
