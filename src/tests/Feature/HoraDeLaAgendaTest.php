<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La hora de una cita es hora de pared.
 *
 * «Las cinco de la tarde» es lo que se acordó con el paciente: no lleva zona
 * horaria porque no la necesita. Si la API la devuelve como instante UTC, la
 * agenda —que lee la hora tal como viene— pinta la cita seis horas más tarde,
 * que es exactamente lo que pasó al mover la aplicación a la hora de Guatemala.
 *
 * Estas pruebas no comprueban una función interna sino lo que sale por el
 * cable, porque el error no estaba en el cálculo sino en la forma del dato.
 */
class HoraDeLaAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function recepcionista(): User
    {
        return User::where('rol', 'recepcionista')->first();
    }

    private function paciente(): Patient
    {
        return Patient::create([
            'nombre' => 'Paciente de las cinco',
            'edad' => 44,
            'telefono' => '+50255511223',
            'lugar_residencia' => 'Guatemala',
            'estado_civil' => 'Casado/a',
            'estado' => 'Activo',
        ]);
    }

    public function test_la_cita_de_las_cinco_de_la_tarde_se_lee_a_las_cinco(): void
    {
        $creada = $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/appointments', [
                'patient_id' => $this->paciente()->id,
                'fecha_hora_inicio' => '2026-09-05 17:00:00',
                'fecha_hora_fin' => '2026-09-05 17:30:00',
                'motivo' => 'Control',
            ])
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('2026-09-05 17:00:00', $creada['fecha_hora_inicio']);
        $this->assertSame('2026-09-05 17:30:00', $creada['fecha_hora_fin']);

        // Y al releerla, que es donde el calendario la pinta
        $leida = $this->actingAs($this->recepcionista(), 'sanctum')
            ->getJson("/api/v1/appointments/{$creada['id']}")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame('2026-09-05 17:00:00', $leida['fecha_hora_inicio']);
    }

    public function test_el_listado_de_la_agenda_devuelve_la_misma_hora(): void
    {
        $paciente = $this->paciente();

        foreach ([['08:00', '08:30'], ['13:30', '14:00'], ['17:00', '17:30'], ['19:45', '20:15']] as [$inicio, $fin]) {
            $this->actingAs($this->recepcionista(), 'sanctum')
                ->postJson('/api/v1/appointments', [
                    'patient_id' => $paciente->id,
                    'fecha_hora_inicio' => "2026-09-05 {$inicio}:00",
                    'fecha_hora_fin' => "2026-09-05 {$fin}:00",
                    'motivo' => 'Control',
                ])
                ->assertStatus(201, "No se pudo agendar la cita de las {$inicio}.");
        }

        $horas = collect($this->actingAs($this->recepcionista(), 'sanctum')
            ->getJson('/api/v1/appointments?date=2026-09-05')
            ->assertStatus(200)
            ->json('data'))
            ->map(fn (array $cita) => substr($cita['fecha_hora_inicio'], 11, 5))
            ->all();

        $this->assertSame(['08:00', '13:30', '17:00', '19:45'], $horas);
    }

    /**
     * Las de la tarde son las que se corren de día al convertirse a UTC: a las
     * seis de la tarde en Guatemala ya es medianoche del día siguiente.
     */
    public function test_una_cita_de_la_tarde_no_se_pasa_al_dia_siguiente(): void
    {
        $creada = $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/appointments', [
                'patient_id' => $this->paciente()->id,
                'fecha_hora_inicio' => '2026-09-05 19:00:00',
                'fecha_hora_fin' => '2026-09-05 19:30:00',
                'motivo' => 'Control',
            ])
            ->assertStatus(201)
            ->json('data');

        $this->assertStringStartsWith('2026-09-05', $creada['fecha_hora_inicio']);
    }

    /**
     * Las fechas de calendario del resto del sistema viajan como fechas y no
     * como instantes, por la misma razón.
     */
    public function test_las_fechas_del_sistema_viajan_sin_zona_horaria(): void
    {
        $historia = \App\Models\ClinicalHistory::create([
            'patient_id' => $this->paciente()->id,
            'fecha_consulta' => '2026-09-05',
            'consulta_por' => 'Enfermedad',
            'ubicacion_patologia' => 'BILATERAL',
            'estado_registro' => 'Finalizada',
        ]);

        $leida = $this->actingAs($this->recepcionista(), 'sanctum')
            ->getJson("/api/v1/clinical-histories/{$historia->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame('2026-09-05', $leida['fecha_consulta']);

        $documento = $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', [
                'patient_id' => $this->paciente()->id,
                'tipo' => 'recibo',
                'fecha_emision' => '2026-09-05',
                'nombre_receptor' => 'Paciente de las cinco',
                'metodo_pago' => 'Efectivo',
                'items' => [['descripcion' => 'Consulta', 'cantidad' => 1, 'precio_unitario' => 350]],
            ])
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('2026-09-05', $documento['fecha_emision']);
    }
}
