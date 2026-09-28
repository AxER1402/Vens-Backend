<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Support\Ajustes\Ajustes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El recordatorio de WhatsApp, un día antes de la cita.
 *
 * La cita de las cinco de la tarde del jueves recibe su mensaje el miércoles a
 * las cinco. El servicio de WhatsApp Web se sustituye con Http::fake(): lo que
 * se prueba es a quién se le pide mandar qué, y cuándo.
 */
class RecordatoriosWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        config([
            'services.whatsapp.url' => 'http://whatsapp.test',
            'services.whatsapp.token' => 'secreto',
            'services.whatsapp.codigo_pais' => '502',
        ]);

        // Miércoles 30 de septiembre de 2026, 17:00.
        Carbon::setTestNow(Carbon::parse('2026-09-30 17:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(string $rol): User
    {
        return User::where('rol', $rol)->first();
    }

    private function cita(string $inicio, array $cambios = []): Appointment
    {
        $paciente = Patient::create([
            'nombre' => 'Ana López',
            'edad' => 40,
            'telefono' => '55512345',
            'lugar_residencia' => 'Guatemala',
            'estado_civil' => 'Casado/a',
            'estado' => 'Activo',
        ]);

        return Appointment::create(array_merge([
            'patient_id' => $paciente->id,
            'medico_id' => $this->usuario('medico')->id,
            'created_by' => $this->usuario('medico')->id,
            'fecha_hora_inicio' => $inicio,
            'fecha_hora_fin' => Carbon::parse($inicio)->addMinutes(30),
            'motivo' => 'Control',
            'estado' => 'Programada',
        ], $cambios));
    }

    private function fingirServicio(int $estado = 200): void
    {
        Http::fake(['whatsapp.test/*' => Http::response(['enviado' => $estado === 200], $estado)]);
    }

    public function test_apagado_no_manda_nada(): void
    {
        $this->fingirServicio();
        $this->cita('2026-10-01 17:00:00');

        $this->artisan('citas:recordar-whatsapp')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_manda_el_recordatorio_a_la_misma_hora_un_dia_antes(): void
    {
        Ajustes::guardar(['whatsapp.recordatorios' => true]);
        $this->fingirServicio();
        $cita = $this->cita('2026-10-01 17:00:00');

        $this->artisan('citas:recordar-whatsapp')->assertSuccessful();

        Http::assertSent(function (Request $peticion) {
            return $peticion->url() === 'http://whatsapp.test/enviar'
                && $peticion->header('X-Token')[0] === 'secreto'
                && $peticion['telefono'] === '50255512345'
                && str_contains($peticion['mensaje'], 'Ana López')
                && str_contains($peticion['mensaje'], 'jueves 1 de octubre')
                && str_contains($peticion['mensaje'], '5:00 PM');
        });

        $cita->refresh();
        $this->assertNotNull($cita->recordatorio_at);
        $this->assertSame('enviado', $cita->recordatorio_resultado);
    }

    public function test_no_manda_antes_de_tiempo(): void
    {
        Ajustes::guardar(['whatsapp.recordatorios' => true]);
        $this->fingirServicio();

        // Faltan 24 horas y un minuto: todavía no.
        $this->cita('2026-10-01 17:01:00');

        $this->artisan('citas:recordar-whatsapp')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_no_lo_manda_dos_veces(): void
    {
        Ajustes::guardar(['whatsapp.recordatorios' => true]);
        $this->fingirServicio();
        $this->cita('2026-10-01 17:00:00');

        $this->artisan('citas:recordar-whatsapp');
        Carbon::setTestNow(Carbon::parse('2026-09-30 17:01:00'));
        $this->artisan('citas:recordar-whatsapp');

        Http::assertSentCount(1);
    }

    public function test_si_el_servicio_estaba_caido_lo_manda_al_volver(): void
    {
        Ajustes::guardar(['whatsapp.recordatorios' => true]);
        $cita = $this->cita('2026-10-01 17:00:00');

        // La primera vez no hay teléfono vinculado; la segunda, sí.
        Http::fake(['whatsapp.test/*' => Http::sequence()
            ->push(['error' => 'WhatsApp no está conectado.'], 503)
            ->push(['enviado' => true], 200)]);

        $this->artisan('citas:recordar-whatsapp');
        $this->assertNull($cita->refresh()->recordatorio_at);

        // Veinte minutos después: sigue dentro del margen.
        Carbon::setTestNow(Carbon::parse('2026-09-30 17:20:00'));
        $this->artisan('citas:recordar-whatsapp');

        $this->assertSame('enviado', $cita->refresh()->recordatorio_resultado);
    }

    public function test_no_recuerda_una_cita_cancelada(): void
    {
        Ajustes::guardar(['whatsapp.recordatorios' => true]);
        $this->fingirServicio();
        $this->cita('2026-10-01 17:00:00', ['estado' => 'Cancelada']);

        $this->artisan('citas:recordar-whatsapp');

        Http::assertNothingSent();
    }

    public function test_un_numero_sin_whatsapp_no_se_reintenta(): void
    {
        Ajustes::guardar(['whatsapp.recordatorios' => true]);
        $this->fingirServicio(404);
        $cita = $this->cita('2026-10-01 17:00:00');

        $this->artisan('citas:recordar-whatsapp');

        $this->assertSame('sin_whatsapp', $cita->refresh()->recordatorio_resultado);
    }

    public function test_reagendar_la_cita_pide_un_recordatorio_nuevo(): void
    {
        Ajustes::guardar(['whatsapp.recordatorios' => true]);
        $this->fingirServicio();
        $cita = $this->cita('2026-10-01 17:00:00');

        $this->artisan('citas:recordar-whatsapp');
        $this->assertNotNull($cita->refresh()->recordatorio_at);

        $cita->update(['fecha_hora_inicio' => '2026-10-05 09:00:00']);

        $this->assertNull($cita->refresh()->recordatorio_at);
    }

    public function test_el_medico_y_el_administrador_encienden_los_recordatorios(): void
    {
        Http::fake(['whatsapp.test/*' => Http::response(['estado' => 'conectado', 'qr' => null, 'numero' => '50255550000'])]);

        foreach (['medico', 'administrador'] as $rol) {
            $this->actingAs($this->usuario($rol), 'sanctum')
                ->putJson('/api/v1/whatsapp', ['recordatorios' => true])
                ->assertStatus(200)
                ->assertJsonPath('data.recordatorios', true)
                ->assertJsonPath('data.conexion.estado', 'conectado');
        }

        $this->assertTrue(Ajustes::obtener('whatsapp.recordatorios'));
    }

    public function test_recepcion_no_toca_los_recordatorios(): void
    {
        $this->actingAs($this->usuario('recepcionista'), 'sanctum')
            ->putJson('/api/v1/whatsapp', ['recordatorios' => true])
            ->assertStatus(403);
    }

    public function test_sin_servicio_la_pantalla_igual_responde(): void
    {
        Http::fake(fn () => throw new ConnectionException('apagado'));

        $this->actingAs($this->usuario('medico'), 'sanctum')
            ->getJson('/api/v1/whatsapp')
            ->assertStatus(200)
            ->assertJsonPath('data.recordatorios', false)
            ->assertJsonPath('data.conexion.estado', 'sin_servicio');
    }
}
