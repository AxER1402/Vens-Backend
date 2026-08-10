<?php

namespace Tests\Feature;

use App\Models\ClinicalHistory;
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
            "{$lado}_cayado_int" => 'Suficiente.',
            "{$lado}_cayado_int_diam" => $diametro,
            "{$lado}_tronco_int" => 'Permeable, suficiente.',
            "{$lado}_tronco_int_diam" => $diametro - 0.5,
            "{$lado}_cayado_ext" => 'Suficiente.',
            "{$lado}_cayado_ext_diam" => 3.2,
            "{$lado}_tronco_ext" => 'Permeable, suficiente.',
            "{$lado}_tronco_ext_diam" => 2.8,
            "{$lado}_perforantes" => 'No se observan perforantes insuficientes.',
            "{$lado}_trombosis" => 'No se observan signos de trombosis en los vasos evaluados.',
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
                    'der_cayado_int' => 'Suficiente.',
                    'izq_perforantes' => 'No se observan perforantes insuficientes.',
                ],
            ]);

        $this->assertDatabaseHas('doppler_reports', [
            'patient_id' => $patient->id,
            'clinical_history_id' => $consulta->id,
            'der_cayado_int_diam' => 4.1,
            'izq_cayado_int_diam' => 5.0,
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

    public function test_diameters_are_validated_as_numbers_within_valid_ranges(): void
    {
        $patient = $this->paciente();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->postJson('/api/v1/doppler-reports', [
                'patient_id' => $patient->id,
                'estado_registro' => 'Borrador',
                'der_cayado_int_diam' => 'cuatro',
                'der_tronco_int_diam' => -1,
                'izq_cayado_ext_diam' => 250,
                'fecha_estudio' => now()->addWeek()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'der_cayado_int_diam',
                'der_tronco_int_diam',
                'izq_cayado_ext_diam',
                'fecha_estudio',
            ]);
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
                'der_cayado_int_diam' => 6.4,
                'conclusion' => 'Insuficiencia del cayado de safena interna derecha.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'der_cayado_int_diam' => '6.40',
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
