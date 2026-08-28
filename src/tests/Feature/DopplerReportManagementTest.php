<?php

namespace Tests\Feature;

use App\Models\ClinicalHistory;
use App\Models\DopplerReport;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DopplerReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function paciente(string $nombre = 'María Elena Portillo'): Patient
    {
        return Patient::create([
            'nombre' => $nombre,
            'edad' => 42,
            'telefono' => '+50378451200',
            'lugar_residencia' => 'Santa Ana',
            'estado_civil' => 'Casado/a',
            'religion' => 'Católica',
            'estado' => 'Activo',
        ]);
    }

    private function consulta(Patient $patient): ClinicalHistory
    {
        return ClinicalHistory::create([
            'patient_id' => $patient->id,
            'fecha_consulta' => now()->toDateString(),
            'estado_registro' => 'Borrador',
        ]);
    }

    private function medico(): User
    {
        return User::where('rol', 'medico')->first();
    }

    /**
     * Hallazgos de un miembro tal como los envía el formulario del Ecodöppler.
     *
     * @return array<string, string|float>
     */
    private function hallazgos(string $lado, float $diametro): array
    {
        return [
            "{$lado}_profundo" => 'Eje venoso profundo permeable y compresible en toda su extensión.',
            "{$lado}_segmentos" => $this->segmentos($diametro),
            "{$lado}_perforantes" => 'No se observan perforantes insuficientes.',
            "{$lado}_trombosis" => 'No se observan signos de trombosis en los vasos evaluados.',
        ];
    }

    /**
     * Las cinco posiciones que informa un miembro: los tres segmentos fijos y
     * los dos que nombra el médico. La última queda vacía a propósito, que es
     * como suele guardarse cuando no se evaluó nada más.
     *
     * @return array<int, array<string, string|float|null>>
     */
    private function segmentos(float $diametro): array
    {
        return [
            $this->segmento('SFJ', $diametro, 38.5, 1.2, 'Reflujo en el cayado.'),
            $this->segmento('GSV Muslo', $diametro - 0.5, 24.0, 0.8, 'Permeable, suficiente.'),
            $this->segmento('GSV Pierna', 3.2, 18.0, 0.4, 'Permeable, suficiente.'),
            $this->segmento('Perforante de Cockett', 2.8, 12.0, 0.6, 'Insuficiente.'),
            $this->segmento(null, null, null, null, null),
        ];
    }

    /**
     * @return array<string, string|float|null>
     */
    private function segmento(
        ?string $nombre,
        ?float $diametroMax,
        ?float $velocidad,
        ?float $duracion,
        ?string $observaciones,
    ): array {
        return [
            'nombre' => $nombre,
            'diametro_max' => $diametroMax,
            'velocidad' => $velocidad,
            'duracion' => $duracion,
            'observaciones' => $observaciones,
            'diametro' => $diametroMax === null ? null : $diametroMax - 0.4,
        ];
    }

    public function test_doctor_can_register_a_complete_doppler_report(): void
    {
        $patient = $this->paciente();
        $consulta = $this->consulta($patient);

        $payload = array_merge(
            [
                'patient_id' => $patient->id,
                'clinical_history_id' => $consulta->id,
                'fecha_estudio' => now()->toDateString(),
                'conclusion' => 'Sistema venoso evaluado permeable sin datos de insuficiencia ni trombosis.',
            ],
            $this->hallazgos('der', 4.1),
            $this->hallazgos('izq', 5.0),
        );

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'patient_id' => $patient->id,
                    'clinical_history_id' => $consulta->id,
                    'estado_registro' => 'Finalizada',
                    'izq_perforantes' => 'No se observan perforantes insuficientes.',
                ],
            ]);

        // Los segmentos se guardan como lista ordenada: la posición identifica
        // al segmento y el nombre viaja con él
        $reporte = DopplerReport::first();
        $this->assertCount(5, $reporte->der_segmentos);
        $this->assertSame('SFJ', $reporte->der_segmentos[0]['nombre']);
        $this->assertSame(4.1, $reporte->der_segmentos[0]['diametro_max']);
        $this->assertSame(38.5, $reporte->der_segmentos[0]['velocidad']);
        $this->assertSame(1.2, $reporte->der_segmentos[0]['duracion']);
        $this->assertSame('Perforante de Cockett', $reporte->der_segmentos[3]['nombre']);
        $this->assertNull($reporte->izq_segmentos[4]['nombre']);
        // JSON no distingue 5 de 5.0, por eso aquí la comparación es laxa
        $this->assertEquals(5.0, $reporte->izq_segmentos[0]['diametro_max']);

        $this->assertDatabaseHas('doppler_reports', [
            'patient_id' => $patient->id,
            'clinical_history_id' => $consulta->id,
            'estado_registro' => 'Finalizada',
            'created_by' => $this->medico()->id,
            'updated_by' => $this->medico()->id,
        ]);
    }

    public function test_draft_can_be_saved_with_only_the_patient(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'estado_registro' => 'Borrador',
                    // Al no enviarse, el estudio se fecha el día de hoy
                    'fecha_estudio' => now()->toDateString(),
                ],
            ]);
    }

    public function test_report_cannot_be_saved_without_a_patient(): void
    {
        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'estado_registro' => 'Borrador',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    public function test_finalizing_requires_the_conclusion(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'conclusion' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('conclusion');
    }

    public function test_segment_measures_must_be_non_negative_numbers(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
                'der_segmentos' => [
                    ['nombre' => 'SFJ', 'diametro' => 'cuatro'],
                    ['nombre' => 'GSV Muslo', 'diametro_max' => -1],
                    ['nombre' => 'GSV Pierna', 'velocidad' => 'rápida'],
                ],
                'izq_segmentos' => [
                    ['nombre' => 'SFJ', 'duracion' => -3],
                ],
                'fecha_estudio' => now()->addWeek()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'der_segmentos.0.diametro',
                'der_segmentos.1.diametro_max',
                'der_segmentos.2.velocidad',
                'izq_segmentos.0.duracion',
                'fecha_estudio',
            ]);
    }

    public function test_segment_measures_have_no_upper_limit(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
                'der_segmentos' => [
                    [
                        'nombre' => 'SFJ',
                        'diametro_max' => 180.5,
                        'velocidad' => 1250,
                        'duracion' => 140,
                        'diametro' => 175,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $segmento = DopplerReport::first()->der_segmentos[0];
        $this->assertEquals(180.5, $segmento['diametro_max']);
        $this->assertEquals(1250, $segmento['velocidad']);
    }

    public function test_report_cannot_be_attached_to_a_consultation_of_another_patient(): void
    {
        $patient = $this->paciente();
        $otro = $this->paciente('Roberto Carlos Martínez');
        $consultaAjena = $this->consulta($otro);

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'clinical_history_id' => $consultaAjena->id,
                'estado_registro' => 'Borrador',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('clinical_history_id');
    }

    public function test_receptionist_cannot_register_a_doppler_report(): void
    {
        $patient = $this->paciente();
        $recepcionista = User::where('rol', 'recepcionista')->first();

        $response = $this->actingAs($recepcionista, 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('doppler_reports', 0);
    }

    public function test_doctor_can_update_an_existing_report(): void
    {
        $patient = $this->paciente();

        $creado = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', array_merge(
                [
                    'patient_id' => $patient->id,
                    'conclusion' => 'Sistema venoso permeable.',
                ],
                $this->hallazgos('der', 4.1),
            ))->json('data.id');

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->putJson("/api/v1/doppler-reports/{$creado}", [
                'der_segmentos' => [
                    ['nombre' => 'SFJ', 'diametro' => 6.4, 'observaciones' => 'Reflujo mayor a 1 s.'],
                ],
                'conclusion' => 'Insuficiencia del cayado de safena interna derecha.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'der_segmentos' => [
                        ['nombre' => 'SFJ', 'diametro' => 6.4, 'observaciones' => 'Reflujo mayor a 1 s.'],
                    ],
                    'conclusion' => 'Insuficiencia del cayado de safena interna derecha.',
                ],
            ]);
    }

    public function test_finalized_report_keeps_its_conclusion_on_a_partial_update(): void
    {
        $patient = $this->paciente();

        $creado = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'conclusion' => 'Sistema venoso permeable.',
            ])->json('data.id');

        // La conclusión guardada satisface el requisito de cierre aunque el
        // formulario solo envíe el campo que se corrigió
        $this->actingAs($this->medico(), 'sanctum')
            ->patchJson("/api/v1/doppler-reports/{$creado}", [
                'der_perforantes' => 'Perforante insuficiente en tercio medio.',
            ])->assertStatus(200);
    }

    public function test_reports_can_be_listed_by_patient_and_by_consultation(): void
    {
        $patient = $this->paciente();
        $consulta = $this->consulta($patient);

        $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'clinical_history_id' => $consulta->id,
                'conclusion' => 'Sistema venoso permeable.',
            ])->assertStatus(201);

        $this->actingAs($this->medico(), 'sanctum')
            ->getJson("/api/v1/patients/{$patient->id}/doppler-reports")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_id', $patient->id);

        $this->actingAs($this->medico(), 'sanctum')
            ->getJson("/api/v1/clinical-histories/{$consulta->id}/doppler-reports")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.clinical_history_id', $consulta->id);
    }

    public function test_report_is_deactivated_logically_and_hidden_from_listings(): void
    {
        $patient = $this->paciente();

        $creado = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'conclusion' => 'Sistema venoso permeable.',
            ])->json('data.id');

        $this->actingAs($this->medico(), 'sanctum')
            ->deleteJson("/api/v1/doppler-reports/{$creado}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['activo' => false]]);

        // El registro sigue existiendo, solo deja de listarse
        $this->assertDatabaseHas('doppler_reports', ['id' => $creado, 'activo' => false]);

        $this->actingAs($this->medico(), 'sanctum')
            ->getJson("/api/v1/patients/{$patient->id}/doppler-reports")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_guest_cannot_read_doppler_reports(): void
    {
        $this->getJson('/api/v1/doppler-reports')->assertStatus(401);
    }
}
