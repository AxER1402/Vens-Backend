<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paginación de los listados.
 *
 * Lo que hay que sostener es que es **opcional**: las mismas rutas alimentan
 * las tablas de la pantalla, que quieren páginas, y los selectores y reportes,
 * que necesitan la lista entera. Si paginar fuera obligatorio, cada uno de esos
 * consumidores tendría que aprender a recorrer páginas.
 */
class PaginacionTest extends TestCase
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

    private function sembrarPacientes(int $cuantos): void
    {
        for ($i = 1; $i <= $cuantos; $i++) {
            Patient::create([
                'nombre' => sprintf('Paciente %03d', $i),
                'edad' => 30,
                'telefono' => '+5025551' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'lugar_residencia' => 'Guatemala',
                'estado_civil' => 'Soltero/a',
                'estado' => 'Activo',
            ]);
        }
    }

    public function test_sin_pedirlo_el_listado_viene_entero(): void
    {
        $this->sembrarPacientes(45);

        $respuesta = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/patients')
            ->assertStatus(200);

        $this->assertCount(45 + Patient::count() - 45, $respuesta->json('data'));
        $this->assertNull($respuesta->json('meta'), 'Sin paginar no hay meta que interpretar.');
    }

    public function test_con_page_el_listado_viene_de_treinta_en_treinta(): void
    {
        $this->sembrarPacientes(45);
        $total = Patient::count();

        $primera = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/patients?page=1')
            ->assertStatus(200);

        $this->assertCount(30, $primera->json('data'));
        $this->assertSame(1, $primera->json('meta.pagina'));
        $this->assertSame(30, $primera->json('meta.por_pagina'));
        $this->assertSame($total, $primera->json('meta.total'));
        $this->assertSame((int) ceil($total / 30), $primera->json('meta.paginas'));

        $segunda = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/patients?page=2')
            ->assertStatus(200);

        $this->assertCount($total - 30, $segunda->json('data'));
        $this->assertSame(2, $segunda->json('meta.pagina'));
    }

    public function test_las_paginas_no_repiten_ni_se_saltan_registros(): void
    {
        $this->sembrarPacientes(45);

        $ids = [];

        for ($pagina = 1; $pagina <= 3; $pagina++) {
            $ids = array_merge($ids, array_column(
                $this->actingAs($this->admin(), 'sanctum')
                    ->getJson("/api/v1/patients?page={$pagina}")
                    ->json('data'),
                'id'
            ));
        }

        $this->assertSame($ids, array_unique($ids), 'Ningún paciente puede salir en dos páginas.');
        $this->assertCount(Patient::count(), $ids, 'Entre todas las páginas tienen que estar todos.');
    }

    public function test_el_filtro_de_busqueda_se_aplica_antes_de_paginar(): void
    {
        $this->sembrarPacientes(45);

        $respuesta = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/patients?search=Paciente 01&page=1')
            ->assertStatus(200);

        // 010 a 019 más 001…009 con ese prefijo: lo que importa es que el total
        // sea el de la búsqueda y no el de la tabla entera.
        $this->assertLessThan(Patient::count(), $respuesta->json('meta.total'));
        $this->assertGreaterThan(0, $respuesta->json('meta.total'));
    }

    public function test_no_se_puede_pedir_una_pagina_desmesurada(): void
    {
        $this->sembrarPacientes(45);

        $respuesta = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/patients?per_page=5000')
            ->assertStatus(200);

        $this->assertSame(200, $respuesta->json('meta.por_pagina'), 'El tope duro es 200.');
    }

    public function test_los_usuarios_tambien_se_paginan(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            User::create([
                'name' => "Usuario {$i}",
                'email' => "usuario{$i}@vens.com",
                'password' => bcrypt('Password123!'),
                'rol' => 'recepcionista',
                'activo' => true,
            ]);
        }

        $respuesta = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/users?page=1')
            ->assertStatus(200);

        $this->assertCount(30, $respuesta->json('data'));
        $this->assertSame(User::count(), $respuesta->json('meta.total'));
    }

    public function test_los_documentos_de_cobro_tambien_se_paginan(): void
    {
        $paciente = Patient::create([
            'nombre' => 'Paciente de cobros',
            'edad' => 40,
            'telefono' => '+50255500000',
            'lugar_residencia' => 'Guatemala',
            'estado_civil' => 'Soltero/a',
            'estado' => 'Activo',
        ]);

        for ($i = 1; $i <= 35; $i++) {
            $documento = Invoice::create([
                'patient_id' => $paciente->id,
                'tipo' => Invoice::TIPO_RECIBO,
                'serie' => 'A',
                'numero' => $i,
                'fecha_emision' => now()->toDateString(),
                'nombre_receptor' => 'Paciente de cobros',
                'total' => 100,
            ]);
            $documento->items()->create([
                'descripcion' => 'Consulta',
                'cantidad' => 1,
                'precio_unitario' => 100,
                'total' => 100,
            ]);
        }

        $respuesta = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/invoices?page=1')
            ->assertStatus(200);

        $this->assertCount(30, $respuesta->json('data'));
        $this->assertSame(35, $respuesta->json('meta.total'));
    }
}
