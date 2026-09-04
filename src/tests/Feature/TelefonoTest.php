<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Support\Contacto\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teléfonos.
 *
 * El problema que se resuelve no es de formato sino de búsqueda: si una
 * persona anota «2222-2222» y otra busca «22222222», la segunda no encuentra a
 * nadie y termina registrando al mismo paciente dos veces. Se guarda un solo
 * formato y se busca con el mismo criterio.
 */
class TelefonoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('rol', 'administrador')->first();
    }

    /** @return array<string, mixed> */
    private function paciente(string $telefono): array
    {
        return [
            'nombre' => 'Paciente de prueba',
            'edad' => 40,
            'telefono' => $telefono,
            'lugar_residencia' => 'Guatemala',
            'estado_civil' => 'Soltero/a',
            'estado' => 'Activo',
        ];
    }

    public function test_los_separadores_se_limpian_al_guardar(): void
    {
        foreach (['2222-2222', '2222 2222', '(502) 2222-2222', '+502 2222 2222'] as $escrito) {
            $guardado = $this->actingAs($this->admin(), 'sanctum')
                ->postJson('/api/v1/patients', $this->paciente($escrito))
                ->assertStatus(201)
                ->json('data.telefono');

            $this->assertMatchesRegularExpression(
                '/^[0-9]+$/',
                $guardado,
                "«{$escrito}» tenía que guardarse solo con dígitos."
            );
        }
    }

    public function test_da_igual_como_se_escriba_al_buscar(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/patients', array_merge(
                $this->paciente('2222-2222'),
                ['nombre' => 'Rosa Amelia Xitumul']
            ))
            ->assertStatus(201);

        // Las cuatro escrituras tienen que dar con la misma persona
        foreach (['22222222', '2222-2222', '2222 2222', '2222'] as $termino) {
            $encontrados = collect($this->actingAs($this->admin(), 'sanctum')
                ->getJson('/api/v1/patients?search='.urlencode($termino))
                ->assertStatus(200)
                ->json('data'))
                ->pluck('nombre');

            $this->assertContains(
                'Rosa Amelia Xitumul',
                $encontrados->all(),
                "Buscar «{$termino}» tenía que encontrarla."
            );
        }
    }

    public function test_lo_que_no_es_un_telefono_se_rechaza(): void
    {
        foreach (['abcdefgh', 'sin teléfono', '----'] as $basura) {
            $this->actingAs($this->admin(), 'sanctum')
                ->postJson('/api/v1/patients', $this->paciente($basura))
                ->assertStatus(422)
                ->assertJsonValidationErrors('telefono');
        }
    }

    public function test_un_telefono_demasiado_corto_no_pasa(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/patients', $this->paciente('2222'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('telefono');
    }

    public function test_el_telefono_del_usuario_sigue_siendo_opcional(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Usuario sin teléfono',
                'email' => 'sin.telefono@vens.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'rol' => 'recepcionista',
                'activo' => true,
            ])
            ->assertStatus(201);
    }

    public function test_el_usuario_tambien_guarda_solo_digitos(): void
    {
        $guardado = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Usuario con teléfono',
                'email' => 'con.telefono@vens.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'rol' => 'recepcionista',
                'activo' => true,
                'telefono' => '5555-4444',
            ])
            ->assertStatus(201)
            ->json('data.telefono');

        $this->assertSame('55554444', $guardado);
    }

    public function test_la_normalizacion_no_inventa_numeros(): void
    {
        $this->assertSame('22222222', Telefono::normalizar('2222-2222'));
        $this->assertSame('50222222222', Telefono::normalizar('+502 2222-2222'));
        $this->assertNull(Telefono::normalizar('sin número'));
        $this->assertNull(Telefono::normalizar(null));
    }

    public function test_los_telefonos_ya_guardados_quedaron_limpios(): void
    {
        // La migración los normalizó; el seeder los sembraba con «+».
        // Se comprueba en PHP y no con REGEXP para no atarse al motor de la
        // base, que en las pruebas no es el mismo que en producción.
        $telefonos = Patient::pluck('telefono')
            ->concat(User::pluck('telefono'))
            ->filter()
            ->all();

        $this->assertNotEmpty($telefonos, 'El seeder debe dejar teléfonos que revisar.');

        foreach ($telefonos as $telefono) {
            $this->assertMatchesRegularExpression(
                '/^[0-9]+$/',
                $telefono,
                "«{$telefono}» quedó con separadores después de la migración."
            );
        }
    }
}
