<?php

namespace Tests\Feature;

use App\Models\ClinicalHistory;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Support\Facturacion\Cantidad;
use App\Support\Facturacion\DatosDocumento;
use App\Support\Reportes\Estadisticos\IngresosPorPeriodo;
use App\Support\Reportes\Estadisticos\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Facturación.
 *
 * Lo que se sostiene aquí es lo que no se ve revisando la pantalla: que las
 * cuentas las hace el servidor y no el navegador, que el correlativo no se
 * repite ni se reutiliza, y que un documento anulado sigue existiendo pero
 * deja de contar como ingreso.
 */
class FacturacionTest extends TestCase
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
            'nombre' => 'Ana Lucía Ramírez',
            'edad' => 47,
            'telefono' => '+50255512345',
            'lugar_residencia' => 'Guatemala',
            'estado_civil' => 'Casado/a',
            'estado' => 'Activo',
        ]);
    }

    /** @return array<string, mixed> */
    private function cobro(array $extra = []): array
    {
        return array_merge([
            'patient_id' => $this->paciente()->id,
            'tipo' => Invoice::TIPO_RECIBO,
            'nombre_receptor' => 'Ana Lucía Ramírez',
            'metodo_pago' => 'Efectivo',
            'items' => [
                ['descripcion' => 'Consulta de flebología', 'cantidad' => 1, 'precio_unitario' => 350],
                ['descripcion' => 'Sesión de escleroterapia', 'cantidad' => 2, 'precio_unitario' => 400, 'descuento' => 50],
            ],
        ], $extra);
    }

    public function test_el_servidor_hace_las_cuentas_y_desglosa_el_iva(): void
    {
        $respuesta = $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())
            ->assertStatus(201);

        // 350 + 800 = 1150 de bruto, menos 50 de descuento = 1100
        $respuesta->assertJsonPath('data.subtotal', '1150.00')
            ->assertJsonPath('data.descuento', '50.00')
            ->assertJsonPath('data.total', '1100.00');

        // El IVA va incluido: 1100 / 1.12 = 982.14 de base, 117.86 de impuesto
        $respuesta->assertJsonPath('data.iva_monto', '117.86')
            ->assertJsonPath('data.iva_porcentaje', '12.00');
    }

    public function test_el_total_que_manda_el_cliente_se_ignora(): void
    {
        $respuesta = $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro([
                'total' => 1,
                'subtotal' => 1,
                'iva_monto' => 0,
            ]))
            ->assertStatus(201);

        $this->assertSame('1100.00', $respuesta->json('data.total'));
    }

    public function test_cada_documento_toma_el_siguiente_correlativo(): void
    {
        $recepcion = $this->recepcionista();

        $primero = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');
        $segundo = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $this->assertSame(1, $primero['numero']);
        $this->assertSame(2, $segundo['numero']);
        $this->assertSame('A', $segundo['serie']);
    }

    public function test_un_documento_anulado_no_libera_su_numero(): void
    {
        $recepcion = $this->recepcionista();

        $primero = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $this->actingAs($recepcion, 'sanctum')
            ->patchJson("/api/v1/invoices/{$primero['id']}/anular", ['motivo_anulacion' => 'Cobro duplicado'])
            ->assertStatus(200);

        $siguiente = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $this->assertSame(2, $siguiente['numero'], 'El correlativo no reutiliza el número de un documento anulado.');
    }

    public function test_el_anulado_deja_de_contar_pero_sigue_existiendo(): void
    {
        $recepcion = $this->recepcionista();

        $documento = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $this->actingAs($recepcion, 'sanctum')
            ->patchJson("/api/v1/invoices/{$documento['id']}/anular", ['motivo_anulacion' => 'Se cobró de más'])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'Anulada')
            ->assertJsonPath('data.motivo_anulacion', 'Se cobró de más');

        $vigentes = $this->actingAs($recepcion, 'sanctum')
            ->getJson('/api/v1/invoices')->assertStatus(200)->json('data');
        $this->assertCount(0, $vigentes);

        $todos = $this->actingAs($recepcion, 'sanctum')
            ->getJson('/api/v1/invoices?incluir_anuladas=1')->assertStatus(200)->json('data');
        $this->assertCount(1, $todos);

        $this->assertDatabaseHas('invoices', ['id' => $documento['id'], 'estado' => 'Anulada']);
    }

    public function test_no_se_puede_anular_dos_veces(): void
    {
        $recepcion = $this->recepcionista();
        $documento = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $this->actingAs($recepcion, 'sanctum')
            ->patchJson("/api/v1/invoices/{$documento['id']}/anular", ['motivo_anulacion' => 'Duplicado'])
            ->assertStatus(200);

        $this->actingAs($recepcion, 'sanctum')
            ->patchJson("/api/v1/invoices/{$documento['id']}/anular", ['motivo_anulacion' => 'Otra vez'])
            ->assertStatus(422);
    }

    public function test_un_documento_sin_renglones_no_se_emite(): void
    {
        $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro(['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_la_factura_exige_nit_y_el_recibo_no(): void
    {
        $recepcion = $this->recepcionista();

        $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro(['tipo' => Invoice::TIPO_FACTURA]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nit_receptor');

        $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())
            ->assertStatus(201)
            ->assertJsonPath('data.nit_receptor', 'CF');
    }

    public function test_la_factura_queda_pendiente_de_certificar_y_no_finge_estarlo(): void
    {
        $respuesta = $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro([
                'tipo' => Invoice::TIPO_FACTURA,
                'nit_receptor' => '1234567-8',
            ]))
            ->assertStatus(201);

        $respuesta->assertJsonPath('data.fel_estado', 'Pendiente');
        $this->assertNull($respuesta->json('data.fel_uuid'), 'Sin certificador no se inventa número de autorización.');
        $this->assertStringContainsString('certificador', (string) $respuesta->json('data.fel_mensaje'));
    }

    public function test_el_recibo_no_pasa_por_el_regimen_electronico(): void
    {
        $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())
            ->assertStatus(201)
            ->assertJsonPath('data.fel_estado', 'No aplica');
    }

    /**
     * La consulta se pasa a cobro desde la propia historia clínica, así que el
     * médico tiene que poder emitir: es quien está delante del paciente cuando
     * la cierra.
     */
    public function test_los_tres_roles_del_mostrador_pueden_cobrar(): void
    {
        foreach (['administrador', 'medico', 'recepcionista'] as $rol) {
            $usuario = User::where('rol', $rol)->first();
            $this->assertNotNull($usuario, "El seeder debe crear un usuario con rol {$rol}.");

            $this->actingAs($usuario, 'sanctum')
                ->postJson('/api/v1/invoices', $this->cobro())
                ->assertStatus(201, "El rol {$rol} no pudo emitir.");
        }
    }

    public function test_el_cobro_puede_quedar_atado_a_una_consulta(): void
    {
        $historia = ClinicalHistory::create([
            'patient_id' => $this->paciente()->id,
            'fecha_consulta' => '2026-09-02',
            'consulta_por' => 'Enfermedad',
            'ubicacion_patologia' => 'BILATERAL',
            'estado_registro' => 'Finalizada',
        ]);

        $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro([
                'patient_id' => $historia->patient_id,
                'clinical_history_id' => $historia->id,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.clinical_history_id', $historia->id);

        $delExpediente = $this->actingAs($this->recepcionista(), 'sanctum')
            ->getJson("/api/v1/invoices?clinical_history_id={$historia->id}")
            ->assertStatus(200)->json('data');

        $this->assertCount(1, $delExpediente);
    }

    public function test_los_renglones_conservan_el_orden_en_que_se_escribieron(): void
    {
        // El tercer renglón es el único que trae 'tipo'. Basta ese detalle para
        // que la petición validada devuelva los índices desordenados.
        $respuesta = $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro([
                'items' => [
                    ['descripcion' => 'Primero', 'cantidad' => 1, 'precio_unitario' => 100],
                    ['descripcion' => 'Segundo', 'cantidad' => 1, 'precio_unitario' => 200],
                    ['descripcion' => 'Tercero', 'tipo' => 'B', 'cantidad' => 1, 'precio_unitario' => 300],
                ],
            ]))
            ->assertStatus(201);

        $this->assertSame(
            ['Primero', 'Segundo', 'Tercero'],
            array_column($respuesta->json('data.items'), 'descripcion'),
            'El documento impreso tiene que listar los conceptos como se teclearon.'
        );
    }

    public function test_el_documento_se_puede_imprimir_en_pdf_y_en_word(): void
    {
        $recepcion = $this->recepcionista();
        $documento = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $pdf = $this->actingAs($recepcion, 'sanctum')
            ->get("/api/v1/invoices/{$documento['id']}/reporte?formato=pdf")
            ->assertStatus(200);
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());

        $word = $this->actingAs($recepcion, 'sanctum')
            ->get("/api/v1/invoices/{$documento['id']}/reporte?formato=docx")
            ->assertStatus(200);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $word->headers->get('Content-Type')
        );
    }

    public function test_el_nombre_del_archivo_dice_qué_documento_es(): void
    {
        $recepcion = $this->recepcionista();
        $documento = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $respuesta = $this->actingAs($recepcion, 'sanctum')
            ->get("/api/v1/invoices/{$documento['id']}/reporte?formato=pdf");

        $this->assertStringContainsString('recibo', $respuesta->headers->get('Content-Disposition'));
    }

    public function test_un_formato_que_no_existe_se_rechaza(): void
    {
        $recepcion = $this->recepcionista();
        $documento = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $this->actingAs($recepcion, 'sanctum')
            ->getJson("/api/v1/invoices/{$documento['id']}/reporte?formato=xlsx")
            ->assertStatus(422);
    }

    public function test_el_medico_puede_imprimir_aunque_no_pueda_cobrar(): void
    {
        $documento = $this->actingAs($this->recepcionista(), 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $this->actingAs(User::where('rol', 'medico')->first(), 'sanctum')
            ->get("/api/v1/invoices/{$documento['id']}/reporte?formato=pdf")
            ->assertStatus(200);
    }

    public function test_el_reporte_de_ingresos_suma_lo_cobrado_y_deja_fuera_lo_anulado(): void
    {
        $recepcion = $this->recepcionista();

        // Dos cobros de 1100 cada uno; uno se anula.
        $primero = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');
        $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $this->actingAs($recepcion, 'sanctum')
            ->patchJson("/api/v1/invoices/{$primero['id']}/anular", ['motivo_anulacion' => 'Cobro duplicado'])
            ->assertStatus(200);

        $reporte = new IngresosPorPeriodo(
            Periodo::entre(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
        );
        $documento = $reporte->construir();

        $meta = $documento['meta'];
        $this->assertSame('1', $meta['Documentos'], 'El anulado no cuenta como documento del período.');
        $this->assertSame('Q 1,100.00', $meta['Total cobrado']);

        $resumen = collect($documento['secciones'])->firstWhere('titulo', 'Resumen');
        $this->assertSame('Q 1,100.00', $resumen['campos']['Total cobrado']);
        $this->assertSame('Q 117.86', $resumen['campos']['IVA incluido']);
        $this->assertSame('Q 982.14', $resumen['campos']['Base imponible']);

        // El anulado no suma, pero se menciona: ocupa un número del correlativo
        $nota = collect($documento['secciones'])->firstWhere('titulo', 'Documentos anulados');
        $this->assertNotNull($nota, 'El reporte tiene que decir qué se anuló en el período.');
        $this->assertStringContainsString('Q 1,100.00', $nota['texto']);
    }

    public function test_el_reporte_de_ingresos_se_emite_aunque_no_haya_cobros(): void
    {
        $this->actingAs($this->recepcionista(), 'sanctum')
            ->get('/api/v1/reportes/ingresos?desde=2020-01-01&hasta=2020-01-31')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * El de ingresos se emite desde la pantalla donde se cobra, no desde el
     * centro de reportes, así que no aparece en el catálogo de aquella.
     */
    public function test_el_reporte_de_ingresos_pertenece_a_facturacion(): void
    {
        $recepcion = $this->recepcionista();

        $deReportes = collect($this->actingAs($recepcion, 'sanctum')
            ->getJson('/api/v1/reportes?modulo=reportes')
            ->assertStatus(200)
            ->json('data'))
            ->pluck('clave');

        $this->assertNotContains('ingresos', $deReportes->all());

        $deFacturacion = collect($this->actingAs($recepcion, 'sanctum')
            ->getJson('/api/v1/reportes?modulo=facturacion')
            ->assertStatus(200)
            ->json('data'))
            ->pluck('clave');

        $this->assertSame(['ingresos'], $deFacturacion->all());
    }

    public function test_el_monto_se_escribe_con_letras_en_el_recibo(): void
    {
        $this->assertSame(
            'Mil ochocientos setenta y cinco quetzales con 00/100',
            Cantidad::enLetras(1875)
        );

        // El uno se apocopa delante del nombre de la moneda
        $this->assertSame('Un quetzal con 00/100', Cantidad::enLetras(1));
        $this->assertSame('Veintiún quetzales con 50/100', Cantidad::enLetras(21.5));
        $this->assertSame('Ciento un quetzales con 00/100', Cantidad::enLetras(101));

        // Y el millón pide «de» solo cuando va pegado al nombre
        $this->assertSame('Un millón de quetzales con 00/100', Cantidad::enLetras(1000000));
        $this->assertStringStartsWith('Un millón doscientos mil quetzales', Cantidad::enLetras(1200000));
    }

    public function test_el_recibo_vigente_sale_marcado_como_pagada(): void
    {
        $recepcion = $this->recepcionista();
        $documento = $this->actingAs($recepcion, 'sanctum')
            ->postJson('/api/v1/invoices', $this->cobro())->json('data');

        $modelo = Invoice::find($documento['id']);
        $this->assertSame('PAGADA', (new DatosDocumento($modelo))->construir()['marca_agua']);

        $this->actingAs($recepcion, 'sanctum')
            ->patchJson("/api/v1/invoices/{$documento['id']}/anular", ['motivo_anulacion' => 'Duplicado'])
            ->assertStatus(200);

        $this->assertSame('ANULADA', (new DatosDocumento($modelo->fresh()))->construir()['marca_agua']);
    }

    public function test_facturar_exige_sesion(): void
    {
        $this->postJson('/api/v1/invoices', $this->cobro())->assertStatus(401);
    }
}
