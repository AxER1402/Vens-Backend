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
            'ubicacion_patologia' => 'BILATERAL',
            'esclero_concentracion' => 1.0,
            'esclero_forma' => 'Espuma',
            'esclero_volumen' => 2.5,
            'evolucion' => 'Mejoría',
            'estado_general' => 'Requiere nuevas sesiones',
            'notas' => 'Paciente tolera bien el procedimiento.',
            'selecciones' => [
                'sintomas' => ['Calambres', 'Pesadez'],
                'ceap_diagnostico' => ['Primaria', 'Superficial'],
                'tx_zonas' => ['Telangiectasias'],
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
                    'selecciones' => [
                        'sintomas' => ['Calambres', 'Pesadez'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'tx_zonas' => ['Telangiectasias'],
                        'enfermedades' => [],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('clinical_histories', [
            'patient_id' => $patient->id,
            'estado_registro' => 'Finalizada',
            'created_by' => $this->medico()->id,
            'updated_by' => $this->medico()->id,
        ]);

        // Las tres listas marcadas deben quedar registradas en la tabla pivote
        $this->assertCount(5, ClinicalHistory::first()->options);
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
                'ultima_menstruacion' => now()->addYear()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'presion_arterial',
                'frecuencia_cardiaca',
                'frecuencia_respiratoria',
                'temperatura',
                'peso',
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
}
