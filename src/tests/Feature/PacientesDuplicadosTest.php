<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PacientesDuplicadosTest extends TestCase
{
    use RefreshDatabase;

    private User $recepcion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->recepcion = User::where('rol', 'recepcionista')->first();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Petrona Xiloj Tum',
            'edad' => 44,
            'telefono' => '4455-6677',
            'lugar_residencia' => 'Chichicastenango',
            'estado_civil' => 'Casado/a',
        ], $cambios);
    }

    private function crear(array $cambios = [])
    {
        return $this->actingAs($this->recepcion, 'sanctum')
            ->postJson('/api/v1/patients', $this->datos($cambios));
    }

    public function test_no_registra_dos_veces_al_mismo_paciente(): void
    {
        $this->crear()->assertStatus(201);

        $this->crear()
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        $this->assertSame(1, Patient::where('telefono', '44556677')->count());
    }

    public function test_reconoce_el_duplicado_aunque_cambien_tildes_mayusculas_y_espacios(): void
    {
        $this->crear(['nombre' => 'María José López'])->assertStatus(201);

        $this->crear(['nombre' => '  maria   jose LOPEZ ', 'telefono' => '44 55 66 77'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_avisa_si_el_duplicado_esta_desactivado(): void
    {
        $this->crear()->assertStatus(201);
        Patient::where('telefono', '44556677')->update(['activo' => false]);

        $respuesta = $this->crear()->assertStatus(422);

        $this->assertStringContainsString('desactivado', $respuesta->json('errors.nombre.0'));
    }

    public function test_permite_otro_paciente_con_el_mismo_telefono(): void
    {
        // Una familia comparte el número: el nombre distinto basta.
        $this->crear()->assertStatus(201);

        $this->crear(['nombre' => 'Juana Xiloj Tum'])->assertStatus(201);
    }

    public function test_al_editar_no_puede_quedar_igual_a_otro_paciente(): void
    {
        $this->crear()->assertStatus(201);
        $this->crear(['nombre' => 'Juana Xiloj Tum'])->assertStatus(201);
        $juana = Patient::where('nombre', 'Juana Xiloj Tum')->first();

        $this->actingAs($this->recepcion, 'sanctum')
            ->putJson("/api/v1/patients/{$juana->id}", ['nombre' => 'Petrona Xiloj Tum'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_editar_otros_datos_no_choca_consigo_mismo(): void
    {
        $this->crear()->assertStatus(201);
        $petrona = Patient::where('telefono', '44556677')->first();

        $this->actingAs($this->recepcion, 'sanctum')
            ->putJson("/api/v1/patients/{$petrona->id}", $this->datos(['edad' => 45]))
            ->assertStatus(200);
    }
}
