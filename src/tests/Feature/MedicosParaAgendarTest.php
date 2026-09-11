<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El personal que se puede elegir como «Médico tratante».
 *
 * Este listado llena un selector, así que lo que importa no es solo a quién
 * incluye sino a quién deja fuera: ofrecer un nombre de más es ofrecer una
 * asignación equivocada, y la agenda es lo que después se lee para saber quién
 * atiende.
 */
class MedicosParaAgendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Administrar el sistema y atender pacientes son cosas distintas aunque
     * coincidan en la misma persona. Si la doctora además administra, lleva dos
     * cuentas; lo que no puede es que la de administración aparezca en la
     * agenda como si pasara consulta.
     */
    public function test_solo_salen_los_medicos(): void
    {
        $roles = collect(
            $this->actingAs($this->recepcionista(), 'sanctum')
                ->getJson('/api/v1/medicos')
                ->assertStatus(200)
                ->json('data')
        )->pluck('rol')->unique();

        $this->assertSame(['medico'], $roles->values()->all());
    }

    public function test_un_medico_desactivado_no_se_puede_agendar(): void
    {
        $medico = User::where('rol', 'medico')->first();
        $medico->update(['activo' => false]);

        $ids = collect(
            $this->actingAs($this->recepcionista(), 'sanctum')
                ->getJson('/api/v1/medicos')
                ->assertStatus(200)
                ->json('data')
        )->pluck('id');

        $this->assertNotContains($medico->id, $ids->all());
    }

    /**
     * Lo pide quien agenda desde el mostrador, que no administra cuentas: si
     * esta ruta exigiera ser administrador, la recepcionista no podría elegir
     * médico al crear la cita.
     */
    public function test_lo_puede_pedir_quien_agenda(): void
    {
        $this->actingAs($this->recepcionista(), 'sanctum')
            ->getJson('/api/v1/medicos')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'rol']]]);
    }

    private function recepcionista(): User
    {
        return User::where('rol', 'recepcionista')->first();
    }
}
