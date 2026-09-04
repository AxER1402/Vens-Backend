<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La sesión del sistema dura una hora exacta: pasado ese plazo el token deja
 * de servir y hay que volver a iniciar sesión.
 */
class SesionExpiraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
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

    public function test_la_configuracion_fija_la_sesion_en_una_hora(): void
    {
        $this->assertSame(60, (int) config('sanctum.expiration'));
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

        Carbon::setTestNow();
    }

    public function test_el_token_sigue_sirviendo_antes_de_la_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        $this->travelTo(Carbon::parse('2026-09-03 08:59:00'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@vens.com');

        $this->travelBack();
        Carbon::setTestNow();
    }

    public function test_el_token_deja_de_servir_cumplida_la_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        $this->travelTo(Carbon::parse('2026-09-03 09:00:01'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->travelBack();
        Carbon::setTestNow();
    }

    public function test_el_perfil_informa_el_tiempo_que_le_queda_a_la_sesion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 08:00:00'));

        $token = $this->iniciarSesion()['access_token'];

        $this->travelTo(Carbon::parse('2026-09-03 08:45:00'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.expires_in', 900)
            ->assertJsonPath('data.expires_at', Carbon::parse('2026-09-03 09:00:00')->toIso8601String());

        $this->travelBack();
        Carbon::setTestNow();
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
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
