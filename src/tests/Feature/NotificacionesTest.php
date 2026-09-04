<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\NotificationDismissal;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Avisos del campanario.
 *
 * Lo que hay que sostener es la ventana —lo que queda de hoy y todo mañana— y
 * que el descarte a mano es de cada usuario y no borra la cita. El resto sale
 * de la agenda y ya está probado en su propio módulo.
 */
class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    /** Un martes cualquiera a media mañana. */
    private const AHORA = '2026-09-08 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Carbon::setTestNow(Carbon::parse(self::AHORA));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function medico(): User
    {
        return User::where('rol', 'medico')->first();
    }

    private function recepcionista(): User
    {
        return User::where('rol', 'recepcionista')->first();
    }

    private function paciente(string $nombre): Patient
    {
        return Patient::create([
            'nombre' => $nombre,
            'edad' => 45,
            'telefono' => '+50378451200',
            'lugar_residencia' => 'Santa Ana',
            'estado_civil' => 'Casado/a',
            'estado' => 'Activo',
        ]);
    }

    private function cita(string $inicio, ?User $medico = null, string $estado = 'Programada'): Appointment
    {
        $medico ??= $this->medico();

        return Appointment::create([
            'patient_id' => $this->paciente('Paciente de '.$inicio)->id,
            'medico_id' => $medico->id,
            'created_by' => $medico->id,
            'fecha_hora_inicio' => $inicio,
            'fecha_hora_fin' => date('Y-m-d H:i:s', strtotime($inicio) + 1800),
            'motivo' => 'Control de escleroterapia',
            'estado' => $estado,
        ]);
    }

    /** @return array<int, string> */
    private function clavesVistasPor(User $usuario): array
    {
        return collect($this->actingAs($usuario, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->json('data.notificaciones'))
            ->pluck('clave')
            ->all();
    }

    public function test_la_ventana_llega_hasta_el_final_de_manana(): void
    {
        $pasada = $this->cita('2026-09-08 08:00:00');
        $hoy = $this->cita('2026-09-08 14:00:00');
        $manana = $this->cita('2026-09-09 09:00:00');
        $ultimaDeManana = $this->cita('2026-09-09 23:30:00');
        $pasadoManana = $this->cita('2026-09-10 09:00:00');

        $claves = $this->clavesVistasPor($this->medico());

        $this->assertSame([
            "cita:{$hoy->id}",
            "cita:{$manana->id}",
            "cita:{$ultimaDeManana->id}",
        ], $claves, 'La ventana va de ahora al final del día de mañana, en orden de agenda.');

        $this->assertNotContains("cita:{$pasada->id}", $claves, 'La cita cuya hora ya pasó se cae sola.');
        $this->assertNotContains("cita:{$pasadoManana->id}", $claves);
    }

    public function test_las_citas_resueltas_no_avisan(): void
    {
        $viva = $this->cita('2026-09-09 09:00:00');
        $cancelada = $this->cita('2026-09-09 10:00:00', estado: 'Cancelada');
        $completada = $this->cita('2026-09-09 11:00:00', estado: 'Completada');

        $claves = $this->clavesVistasPor($this->medico());

        $this->assertSame(["cita:{$viva->id}"], $claves);
        $this->assertNotContains("cita:{$cancelada->id}", $claves);
        $this->assertNotContains("cita:{$completada->id}", $claves);
    }

    public function test_el_aviso_dice_si_es_de_hoy_o_de_manana(): void
    {
        $this->cita('2026-09-08 14:00:00');
        $this->cita('2026-09-09 09:00:00');

        $avisos = $this->actingAs($this->medico(), 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->json('data.notificaciones');

        $this->assertSame(['hoy', 'manana'], array_column($avisos, 'dia'));
        $this->assertSame(['14:00', '09:00'], array_column($avisos, 'hora'));
        $this->assertNotNull($avisos[0]['paciente']['nombre']);
    }

    public function test_el_medico_solo_ve_su_propia_agenda(): void
    {
        $otroMedico = User::create([
            'name' => 'Doctor Suplente',
            'email' => 'suplente@vens.com',
            'password' => bcrypt('Password123!'),
            'rol' => 'medico',
            'activo' => true,
        ]);

        $propia = $this->cita('2026-09-09 09:00:00');
        $ajena = $this->cita('2026-09-09 10:00:00', $otroMedico);

        $this->assertSame(["cita:{$propia->id}"], $this->clavesVistasPor($this->medico()));
        $this->assertSame(["cita:{$ajena->id}"], $this->clavesVistasPor($otroMedico));
    }

    public function test_recepcion_ve_la_agenda_de_la_clinica_entera(): void
    {
        $otroMedico = User::create([
            'name' => 'Doctor Suplente',
            'email' => 'suplente@vens.com',
            'password' => bcrypt('Password123!'),
            'rol' => 'medico',
            'activo' => true,
        ]);

        $una = $this->cita('2026-09-09 09:00:00');
        $otra = $this->cita('2026-09-09 10:00:00', $otroMedico);

        $this->assertSame(
            ["cita:{$una->id}", "cita:{$otra->id}"],
            $this->clavesVistasPor($this->recepcionista())
        );
    }

    public function test_descartar_un_aviso_lo_saca_de_la_lista_sin_tocar_la_cita(): void
    {
        $descartada = $this->cita('2026-09-09 09:00:00');
        $otra = $this->cita('2026-09-09 10:00:00');

        $this->actingAs($this->medico(), 'sanctum')
            ->deleteJson("/api/v1/notifications/cita:{$descartada->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(["cita:{$otra->id}"], $this->clavesVistasPor($this->medico()));
        $this->assertDatabaseHas('appointments', [
            'id' => $descartada->id,
            'estado' => 'Programada',
        ]);
    }

    public function test_el_descarte_es_de_cada_usuario(): void
    {
        $cita = $this->cita('2026-09-09 09:00:00');

        $this->actingAs($this->medico(), 'sanctum')
            ->deleteJson("/api/v1/notifications/cita:{$cita->id}")
            ->assertStatus(200);

        $this->assertSame([], $this->clavesVistasPor($this->medico()));
        $this->assertSame(["cita:{$cita->id}"], $this->clavesVistasPor($this->recepcionista()));
    }

    public function test_descartar_dos_veces_no_duplica_la_marca(): void
    {
        $cita = $this->cita('2026-09-09 09:00:00');
        $medico = $this->medico();

        foreach ([1, 2] as $ignorado) {
            $this->actingAs($medico, 'sanctum')
                ->deleteJson("/api/v1/notifications/cita:{$cita->id}")
                ->assertStatus(200);
        }

        $this->assertDatabaseCount('notification_dismissals', 1);
    }

    public function test_se_pueden_descartar_todos_de_una_vez(): void
    {
        $this->cita('2026-09-08 14:00:00');
        $this->cita('2026-09-09 09:00:00');

        $this->actingAs($this->medico(), 'sanctum')
            ->deleteJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.descartados', 2);

        $this->assertSame([], $this->clavesVistasPor($this->medico()));
    }

    public function test_los_descartes_viejos_se_limpian_solos(): void
    {
        $viejo = NotificationDismissal::create([
            'user_id' => $this->medico()->id,
            'clave' => 'cita:9999',
            'descartada_at' => now()->subMonth(),
        ]);

        $cita = $this->cita('2026-09-09 09:00:00');

        $this->actingAs($this->medico(), 'sanctum')
            ->deleteJson("/api/v1/notifications/cita:{$cita->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('notification_dismissals', ['id' => $viejo->id]);
        $this->assertDatabaseCount('notification_dismissals', 1);
    }

    public function test_los_avisos_exigen_sesion(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }
}
