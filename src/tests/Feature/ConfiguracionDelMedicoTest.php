<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quién mantiene los datos de la clínica y las tarifas.
 *
 * El médico sí: en esta clínica quien atiende es quien la dirige, así que el
 * membrete y el colegiado que firman los informes son suyos, y el precio de lo
 * que indica lo pone él. Recepción no, y ahí está el límite que importa: de los
 * datos de la clínica salen el NIT y la serie de los recibos, y una tarifa no
 * se corrige en el mostrador con un paciente delante.
 *
 * La pantalla esconde los botones a quien solo mira, pero eso no protege nada:
 * lo que se prueba aquí es que quien llame al endpoint a mano reciba el mismo
 * no.
 */
class ConfiguracionDelMedicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function medico(): User
    {
        return User::where('rol', 'medico')->first();
    }

    private function recepcionista(): User
    {
        return User::where('rol', 'recepcionista')->first();
    }

    public function test_el_medico_puede_leer_los_datos_de_la_clinica(): void
    {
        $this->actingAs($this->medico(), 'sanctum')
            ->getJson('/api/v1/ajustes')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_el_medico_puede_leer_el_catalogo_de_tarifas(): void
    {
        Service::create(['nombre' => 'Consulta de flebología', 'precio' => 350, 'activo' => true]);

        $this->actingAs($this->medico(), 'sanctum')
            ->getJson('/api/v1/services')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_el_medico_puede_corregir_los_datos_de_la_clinica(): void
    {
        // Los ajustes viajan agrupados ({clinica: {nombre: …}}) porque así se
        // validan; en la tabla la clave es 'clinica.nombre'.
        $this->actingAs($this->medico(), 'sanctum')
            ->putJson('/api/v1/ajustes', ['clinica' => ['nombre' => 'Clínica de Flebología']])
            ->assertStatus(200);

        // Y al leerlos vuelven con el punto en la clave, así que se toma el
        // arreglo entero: json('data.ajustes.clinica.nombre') leería el punto
        // como anidamiento y no encontraría nada.
        $ajustes = $this->actingAs($this->medico(), 'sanctum')
            ->getJson('/api/v1/ajustes')
            ->json('data.ajustes');

        $this->assertSame('Clínica de Flebología', $ajustes['clinica.nombre']);
    }

    public function test_el_medico_puede_poner_precio_a_lo_que_indica(): void
    {
        $servicio = Service::create(['nombre' => 'Escleroterapia', 'precio' => 400, 'activo' => true]);
        $medico = $this->medico();

        $this->actingAs($medico, 'sanctum')
            ->postJson('/api/v1/services', ['nombre' => 'Control posoperatorio', 'precio' => 150])
            ->assertStatus(201);

        $this->actingAs($medico, 'sanctum')
            ->putJson("/api/v1/services/{$servicio->id}", ['nombre' => 'Escleroterapia', 'precio' => 450])
            ->assertStatus(200);

        $this->assertSame('450.00', $servicio->fresh()->precio);
    }

    /**
     * El límite de verdad. Recepción emite los recibos, así que lee el catálogo
     * y los datos fiscales; cambiarlos es otra cosa.
     */
    public function test_recepcion_mira_pero_no_cambia(): void
    {
        $servicio = Service::create(['nombre' => 'Escleroterapia', 'precio' => 400, 'activo' => true]);
        $recepcion = $this->recepcionista();

        $this->actingAs($recepcion, 'sanctum')->getJson('/api/v1/ajustes')->assertStatus(200);
        $this->actingAs($recepcion, 'sanctum')->getJson('/api/v1/services')->assertStatus(200);

        $this->actingAs($recepcion, 'sanctum')
            ->putJson('/api/v1/ajustes', ['clinica' => ['nombre' => 'Otro nombre']])
            ->assertStatus(403);

        $this->actingAs($recepcion, 'sanctum')
            ->putJson("/api/v1/services/{$servicio->id}", ['nombre' => 'Escleroterapia', 'precio' => 1])
            ->assertStatus(403);

        $this->assertSame('400.00', $servicio->fresh()->precio);
    }
}
