<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El inicio de sesión con Google.
 *
 * Google solo da fe de quién es la persona; quién entra lo sigue decidiendo
 * el administrador. El servicio tokeninfo de Google se sustituye con
 * Http::fake(): lo que se prueba es qué hace el backend con su respuesta.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'cliente-vens.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['services.google.client_id' => self::CLIENT_ID]);
        Storage::fake('public');
    }

    public function test_una_cuenta_registrada_entra_con_google_y_queda_vinculada(): void
    {
        $this->googleResponde(['email' => 'medico@vens.com', 'sub' => '1111']);

        $respuesta = $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(200)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'medico@vens.com');

        $this->assertNotEmpty($respuesta->json('data.access_token'));
        $this->assertNotNull($respuesta->json('data.expires_at'));
        $this->assertSame('1111', User::where('email', 'medico@vens.com')->value('google_id'));
        $this->assertStringContainsString('con Google', AuditLog::where('evento', 'login')->sole()->descripcion);
    }

    /**
     * Ya vinculada, la cuenta se reconoce por el identificador de Google
     * aunque el correo de Google haya cambiado.
     */
    public function test_una_cuenta_vinculada_se_reconoce_aunque_cambie_el_correo(): void
    {
        $this->usuario('medico')->forceFill(['google_id' => '1111'])->save();
        $this->googleResponde(['email' => 'otro-correo@gmail.com', 'sub' => '1111']);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', 'medico@vens.com');
    }

    public function test_google_no_crea_cuentas(): void
    {
        $this->googleResponde(['email' => 'desconocido@gmail.com', 'sub' => '2222']);
        $usuarios = User::count();

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(401);

        $this->assertSame($usuarios, User::count());
        $fallido = AuditLog::where('evento', 'login_fallido')->sole();
        $this->assertSame('desconocido@gmail.com', $fallido->usuario_correo);
    }

    public function test_una_cuenta_desactivada_no_entra(): void
    {
        $this->usuario('medico')->update(['activo' => false]);
        $this->googleResponde(['email' => 'medico@vens.com', 'sub' => '1111']);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(403);

        $this->assertNull(User::where('email', 'medico@vens.com')->value('google_id'));
    }

    /**
     * Si la cuenta ya está vinculada a una cuenta de Google, otra con el
     * mismo correo no la puede tomar.
     */
    public function test_otra_cuenta_de_google_con_el_mismo_correo_no_entra(): void
    {
        $this->usuario('medico')->forceFill(['google_id' => '1111'])->save();
        $this->googleResponde(['email' => 'medico@vens.com', 'sub' => '9999']);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(401);
    }

    public function test_un_token_emitido_para_otra_aplicacion_no_sirve(): void
    {
        $this->googleResponde(['email' => 'medico@vens.com', 'sub' => '1111', 'aud' => 'otra-app.apps.googleusercontent.com']);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(401);
    }

    public function test_un_correo_sin_verificar_no_sirve(): void
    {
        $this->googleResponde(['email' => 'medico@vens.com', 'sub' => '1111', 'email_verified' => 'false']);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(401);
    }

    public function test_un_token_que_google_rechaza_no_sirve(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_token'], 400)]);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-vencido'])
            ->assertStatus(401);
    }

    public function test_sin_client_id_configurado_no_se_consulta_a_google(): void
    {
        config(['services.google.client_id' => null]);
        Http::fake();

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_la_primera_vez_se_trae_la_foto_de_google(): void
    {
        $this->googleResponde(['email' => 'medico@vens.com', 'sub' => '1111'], conFoto: true);

        $respuesta = $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(200);

        $ruta = User::where('email', 'medico@vens.com')->value('foto_path');
        $this->assertNotNull($ruta);
        Storage::disk('public')->assertExists($ruta);
        $this->assertStringEndsWith('.jpg', $ruta);
        $this->assertNotNull($respuesta->json('data.user.foto_url'));

        // Se pide la foto en tamaño grande, no la miniatura.
        Http::assertSent(fn ($peticion) => str_contains($peticion->url(), 'foto-google=s256-c'));
    }

    /**
     * La foto se copia y desde ahí es del sistema: si el usuario la quita, no
     * vuelve a aparecer en el siguiente ingreso con Google.
     */
    public function test_la_foto_de_google_no_vuelve_si_el_usuario_la_quito(): void
    {
        $this->googleResponde(['email' => 'medico@vens.com', 'sub' => '1111'], conFoto: true);
        $token = $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->json('data.access_token');

        $this->withToken($token)->deleteJson('/api/v1/me/foto')->assertStatus(200);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(200);

        $this->assertNull(User::where('email', 'medico@vens.com')->value('foto_path'));
    }

    public function test_no_se_reemplaza_una_foto_que_el_usuario_ya_tenia(): void
    {
        $this->usuario('medico')->update(['foto_path' => 'avatares/propia.png']);
        $this->googleResponde(['email' => 'medico@vens.com', 'sub' => '1111'], conFoto: true);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(200);

        $this->assertSame('avatares/propia.png', User::where('email', 'medico@vens.com')->value('foto_path'));
    }

    public function test_si_la_foto_falla_igual_se_entra(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response($this->datosDeGoogle(['email' => 'medico@vens.com', 'sub' => '1111'])),
            'openidconnect.googleapis.com/*' => Http::response(['picture' => 'https://lh3.googleusercontent.com/a/foto-google=s96-c']),
            'lh3.googleusercontent.com/*' => Http::response('', 500),
        ]);

        $this->postJson('/api/v1/auth/google', ['access_token' => 'token-de-google'])
            ->assertStatus(200);

        $this->assertNull(User::where('email', 'medico@vens.com')->value('foto_path'));
    }

    public function test_la_credencial_es_obligatoria(): void
    {
        $this->postJson('/api/v1/auth/google', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('access_token');
    }

    /**
     * @param  array<string, string>  $datos
     */
    private function googleResponde(array $datos, bool $conFoto = false): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response($this->datosDeGoogle($datos)),
            'openidconnect.googleapis.com/*' => Http::response(
                $conFoto ? ['picture' => 'https://lh3.googleusercontent.com/a/foto-google=s96-c'] : []
            ),
            'lh3.googleusercontent.com/*' => Http::response('imagen', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    /**
     * @param  array<string, string>  $datos
     * @return array<string, string>
     */
    private function datosDeGoogle(array $datos): array
    {
        return $datos + [
            'aud' => self::CLIENT_ID,
            'email_verified' => 'true',
        ];
    }

    private function usuario(string $rol): User
    {
        return User::where('rol', $rol)->firstOrFail();
    }
}
