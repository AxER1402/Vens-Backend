<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La bitácora de auditoría.
 *
 * Lo que importa es que lo que el administrador viene a buscar esté —quién
 * entró, quién no pudo, quién cambió qué cuenta— y que nadie más pueda leerlo.
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_el_inicio_de_sesion_queda_anotado(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'medico@vens.com',
            'password' => 'Password123!',
        ])->assertStatus(200);

        $fila = AuditLog::where('evento', 'login')->sole();

        $this->assertSame($this->usuario('medico')->id, $fila->user_id);
        $this->assertSame('sesion', $fila->categoria);
        $this->assertNotNull($fila->ip);
    }

    /**
     * También sin cuenta: una ráfaga contra un correo inventado es justo lo
     * que hay que ver.
     */
    public function test_los_intentos_fallidos_quedan_anotados_con_el_correo(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'medico@vens.com',
            'password' => 'otra-cosa',
        ])->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nadie@vens.com',
            'password' => 'otra-cosa',
        ])->assertStatus(401);

        $fallidos = AuditLog::where('evento', 'login_fallido')->orderBy('id')->get();

        $this->assertCount(2, $fallidos);
        $this->assertSame($this->usuario('medico')->id, $fallidos[0]->user_id);
        $this->assertNull($fallidos[1]->user_id);
        $this->assertSame('nadie@vens.com', $fallidos[1]->usuario_correo);
    }

    public function test_el_cierre_de_sesion_anota_cuanto_duro(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@vens.com',
            'password' => 'Password123!',
        ])->json('data.access_token');

        $this->travel(45)->minutes();

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertStatus(200);

        $fila = AuditLog::where('evento', 'logout')->sole();

        $this->assertStringContainsString('45 min', $fila->descripcion);
    }

    /**
     * La contraseña no se copia, ni siquiera el hash: basta con saber que cambió.
     */
    public function test_modificar_un_usuario_guarda_el_antes_y_el_despues(): void
    {
        $medico = $this->usuario('medico');

        $this->actingAs($this->usuario('administrador'), 'sanctum')
            ->putJson("/api/v1/users/{$medico->id}", [
                'name' => 'Dra. Cambiada',
                'password' => 'NuevaClave123!',
                'password_confirmation' => 'NuevaClave123!',
            ])
            ->assertStatus(200);

        $fila = AuditLog::where('evento', 'usuario_actualizado')->sole();

        $this->assertSame($medico->name, $fila->cambios['name']['antes']);
        $this->assertSame('Dra. Cambiada', $fila->cambios['name']['despues']);
        $this->assertSame('(cambiada)', $fila->cambios['password']['despues']);
        $this->assertStringNotContainsString('$2y$', json_encode($fila->cambios));
    }

    public function test_crear_y_desactivar_quedan_anotados(): void
    {
        $admin = $this->usuario('administrador');

        $id = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Nueva Recepción',
                'email' => 'nueva@vens.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'rol' => 'recepcionista',
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/users/{$id}")
            ->assertStatus(200);

        $this->assertSame(
            ['usuario_creado', 'usuario_desactivado'],
            AuditLog::where('sujeto_id', $id)->orderBy('id')->pluck('evento')->all()
        );
    }

    public function test_solo_el_administrador_puede_leerla(): void
    {
        foreach (['medico', 'recepcionista', 'enfermera'] as $rol) {
            $this->actingAs($this->usuario($rol), 'sanctum')
                ->getJson('/api/v1/auditoria')
                ->assertStatus(403);
        }

        $this->actingAs($this->usuario('administrador'), 'sanctum')
            ->getJson('/api/v1/auditoria?page=1')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['total']]);
    }

    public function test_se_filtra_por_evento_y_por_usuario(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'medico@vens.com', 'password' => 'Password123!']);
        $this->postJson('/api/v1/auth/login', ['email' => 'recepcion@vens.com', 'password' => 'mal']);

        $medico = $this->usuario('medico');

        $filas = $this->actingAs($this->usuario('administrador'), 'sanctum')
            ->getJson("/api/v1/auditoria?evento=login&user_id={$medico->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $filas);
        $this->assertSame('login', $filas[0]['evento']);
    }

    public function test_el_resumen_trae_el_ultimo_ingreso_de_cada_usuario(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'medico@vens.com', 'password' => 'Password123!']);
        $this->postJson('/api/v1/auth/login', ['email' => 'medico@vens.com', 'password' => 'mal']);

        $respuesta = $this->actingAs($this->usuario('administrador'), 'sanctum')
            ->getJson('/api/v1/auditoria/resumen')
            ->assertStatus(200);

        $medico = collect($respuesta->json('data.usuarios'))->firstWhere('email', 'medico@vens.com');

        $this->assertNotNull($medico['ultimo_ingreso']);
        $this->assertSame(1, $medico['ingresos_semana']);
        $this->assertSame(1, $medico['fallidos_semana']);
        $this->assertSame(1, $medico['sesiones_abiertas']);
        $this->assertSame(1, $respuesta->json('data.totales.ingresos_hoy'));
    }

    private function usuario(string $rol): User
    {
        return User::where('rol', $rol)->firstOrFail();
    }
}
