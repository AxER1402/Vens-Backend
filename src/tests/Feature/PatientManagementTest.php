<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_can_list_patients(): void
    {
        $user = User::where('rol', 'recepcionista')->first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/patients');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'nombre',
                        'edad',
                        'telefono',
                        'lugar_residencia',
                        'estado_civil',
                        'religion',
                        'estado',
                        'activo',
                    ]
                ]
            ]);
    }

    public function test_doctor_or_admin_can_create_patient(): void
    {
        $doctor = User::where('rol', 'medico')->first();

        $payload = [
            'nombre' => 'Carlos Alberto Fuentes',
            'edad' => 50,
            'telefono' => '+50379998877',
            'lugar_residencia' => 'San Salvador',
            'estado_civil' => 'Casado/a',
            'religion' => 'Católica',
            'estado' => 'Activo',
        ];

        $response = $this->actingAs($doctor, 'sanctum')
            ->postJson('/api/v1/patients', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Paciente registrado exitosamente.',
                'data' => [
                    'nombre' => 'Carlos Alberto Fuentes',
                    'edad' => 50,
                    'activo' => true,
                ]
            ]);

        $this->assertDatabaseHas('patients', [
            'nombre' => 'Carlos Alberto Fuentes',
            'telefono' => '+50379998877',
        ]);
    }

    public function test_receptionist_can_create_patient(): void
    {
        $receptionist = User::where('rol', 'recepcionista')->first();

        $payload = [
            'nombre' => 'Laura Vanessa Morales',
            'edad' => 28,
            'telefono' => '+50375554433',
            'lugar_residencia' => 'San Salvador',
            'estado_civil' => 'Soltero/a',
            'religion' => 'Ninguna',
            'estado' => 'Activo',
        ];

        $response = $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/v1/patients', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nombre' => 'Laura Vanessa Morales',
                ]
            ]);
    }

    public function test_receptionist_cannot_deactivate_patient(): void
    {
        $receptionist = User::where('rol', 'recepcionista')->first();
        $patient = Patient::where('activo', true)->first();

        $response = $this->actingAs($receptionist, 'sanctum')
            ->deleteJson("/api/v1/patients/{$patient->id}");

        $response->assertStatus(403);
    }

    public function test_doctor_can_update_patient(): void
    {
        $doctor = User::where('rol', 'medico')->first();
        $patient = Patient::first();

        $payload = [
            'estado' => 'Seguimiento',
            'lugar_residencia' => 'Nuevo Cuscatlán',
        ];

        $response = $this->actingAs($doctor, 'sanctum')
            ->putJson("/api/v1/patients/{$patient->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $patient->id,
                    'estado' => 'Seguimiento',
                    'lugar_residencia' => 'Nuevo Cuscatlán',
                ]
            ]);
    }

    public function test_doctor_or_admin_can_deactivate_patient(): void
    {
        $admin = User::where('rol', 'administrador')->first();
        $patient = Patient::where('activo', true)->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/patients/{$patient->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Paciente desactivado exitosamente.',
                'data' => [
                    'id' => $patient->id,
                    'activo' => false,
                ]
            ]);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'activo' => false,
        ]);
    }
}
