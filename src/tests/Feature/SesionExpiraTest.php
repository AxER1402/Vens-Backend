<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La sesión del sistema se cierra por inactividad, no a plazo fijo: la hora se
 * cuenta desde la última petición hecha con el token. Quien trabaja no pierde
 * la sesión; la pantalla que se quedó abierta se cierra sola.
 */
class SesionExpiraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function iniciarSesion(): array
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@vens.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);

        return $response->json('data');
    }

    /**
     * Cada petición se hace desde cero: dentro de una misma prueba el guard
     * recuerda al usuario que ya resolvió y no volvería a comprobar el plazo.
     */
    private function pedirPerfil(string $token): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');
    }

    public function test_la_configuracion_fija_la_inactividad_en_una_hora(): void
    {
        $this->assertSame(60, (int) config('sanctum.inactividad'));

        // La caducidad a plazo fijo de Sanctum queda apagada a propósito: si
        // volviera a tener valor, cerraría la sesión aunque hubiera actividad.
        $this->assertNull(config('sanctum.expiration'));
    }

    public function test_el_login_informa_cuando_vence_la_sesion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $datos = $this->iniciarSesion();

        $this->assertSame(3600, $datos['expires_in']);
        $this->assertSame(
            Carbon::parse('2026-09-03 09:00:00')->toIso8601String(),
            $datos['expires_at']
        );
    }

    public function test_el_token_sigue_sirviendo_antes_de_la_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        $this->travelTo(Carbon::parse('2026-09-03 08:59:00'));

        $this->pedirPerfil($token)
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@vens.com');
    }

    public function test_trabajar_mantiene_la_sesion_viva_mas_alla_de_una_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        // A los 45 minutos el usuario sigue en el sistema...
        $this->travelTo(Carbon::parse('2026-09-03 08:45:00'));
        $this->pedirPerfil($token)->assertStatus(200);

        // ...y hora y media después del login la sesión sigue viva, porque el
        // plazo se recontó desde esa última petición.
        $this->travelTo(Carbon::parse('2026-09-03 09:30:00'));
        $this->pedirPerfil($token)->assertStatus(200);
    }

    public function test_el_token_deja_de_servir_tras_una_hora_sin_usarse(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        $this->travelTo(Carbon::parse('2026-09-03 09:00:01'));

        $this->pedirPerfil($token)->assertStatus(401);
    }

    public function test_la_inactividad_se_cuenta_desde_la_ultima_peticion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        $this->travelTo(Carbon::parse('2026-09-03 08:50:00'));
        $this->pedirPerfil($token)->assertStatus(200);

        // Una hora y un segundo después de esa petición, y no del login.
        $this->travelTo(Carbon::parse('2026-09-03 09:50:01'));
        $this->pedirPerfil($token)->assertStatus(401);
    }

    public function test_usar_la_sesion_renueva_el_plazo_que_se_informa(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        $this->travelTo(Carbon::parse('2026-09-03 08:45:00'));

        // La propia petición cuenta como actividad, así que el plazo vuelve a
        // estar completo y vence una hora después de este momento.
        $this->pedirPerfil($token)
            ->assertStatus(200)
            ->assertJsonPath('data.expires_in', 3600)
            ->assertJsonPath('data.expires_at', Carbon::parse('2026-09-03 09:45:00')->toIso8601String());
    }

    public function test_cada_respuesta_anuncia_el_vencimiento_en_las_cabeceras(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        $this->travelTo(Carbon::parse('2026-09-03 08:30:00'));

        $this->pedirPerfil($token)
            ->assertStatus(200)
            ->assertHeader('X-Session-Expires-In', '3600')
            ->assertHeader(
                'X-Session-Expires-At',
                Carbon::parse('2026-09-03 09:30:00')->toIso8601String()
            );
    }

    public function test_al_entrar_se_borran_las_sesiones_ya_vencidas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $this->iniciarSesion();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Al día siguiente, aquel token ya no autentica a nadie: su fila no
        // tiene por qué seguir ahí.
        Carbon::setTestNow(Carbon::parse('2026-09-04 08:00:00'));

        $this->app['auth']->forgetGuards();
        $this->iniciarSesion();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_las_sesiones_vivas_no_se_borran_al_entrar_de_nuevo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        // Otro dispositivo del mismo usuario inicia sesión media hora después.
        $this->travelTo(Carbon::parse('2026-09-03 08:30:00'));
        $this->app['auth']->forgetGuards();
        $this->iniciarSesion();

        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->pedirPerfil($token)->assertStatus(200);
    }

    public function test_cerrar_sesion_invalida_el_token_de_inmediato(): void
    {
        $token = $this->iniciarSesion()['access_token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // El guard recuerda al usuario dentro de la misma prueba, así que se
        // olvida antes de comprobar que el token revocado ya no autentica.
        $this->pedirPerfil($token)->assertStatus(401);
    }

    public function test_en_cero_la_sesion_no_vence(): void
    {
        config(['sanctum.inactividad' => 0]);

        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $datos = $this->iniciarSesion();

        $this->assertNull($datos['expires_in']);
        $this->assertNull($datos['expires_at']);

        $this->travelTo(Carbon::parse('2026-09-10 08:00:00'));

        $this->pedirPerfil($datos['access_token'])
            ->assertStatus(200)
            ->assertHeaderMissing('X-Session-Expires-At');
    }
}
