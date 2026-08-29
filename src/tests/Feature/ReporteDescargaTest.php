<?php

namespace Tests\Feature;

use App\Models\ClinicalHistory;
use App\Models\ClinicalOption;
use App\Models\DopplerReport;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Emisión de los informes clínicos imprimibles.
 *
 * Lo que se comprueba aquí es que el documento sale, que sale en el formato
 * pedido y que se llama como debe. Que además se lea bien es cosa de abrirlo:
 * un test no sabe si una tabla se partió en un sitio feo.
 */
class ReporteDescargaTest extends TestCase
{
    use RefreshDatabase;

    /** PNG de 1x1 px para el mapeo venoso. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const TIPO_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /*
    |--------------------------------------------------------------------------
    | Datos de apoyo
    |--------------------------------------------------------------------------
    */

    private function medico(): User
    {
        return User::where('rol', 'medico')->first();
    }

    private function paciente(): Patient
    {
        return Patient::create([
            'nombre' => 'María Elena Portillo',
            'edad' => 42,
            'telefono' => '+50378451200',
            'lugar_residencia' => 'Santa Ana',
            'estado_civil' => 'Casado/a',
            'religion' => 'Católica',
            'estado' => 'Activo',
        ]);
    }

    /**
     * Consulta con datos en todas las secciones, para que el informe salga
     * completo y no con media plantilla omitida.
     */
    private function consulta(string $estado = 'Finalizada'): ClinicalHistory
    {
        $historia = ClinicalHistory::create([
            'patient_id' => $this->paciente()->id,
            'fecha_consulta' => '2026-08-28',
            'consulta_por' => 'Enfermedad',
            'familiar_varices' => true,
            'alergias' => 'Penicilina',
            'cirugias' => 'Safenectomía derecha (2021)',
            'gestas' => 3,
            'partos' => 2,
            'cesareas' => 1,
            'hijos_vivos' => 3,
            'presion_arterial' => '120/80',
            'frecuencia_cardiaca' => 72,
            'temperatura' => 36.5,
            'peso' => 68.4,
            'perimetro_tobillo' => 24.5,
            'perimetro_pantorrilla' => 36.0,
            'ubicacion_patologia' => 'BILATERAL',
            'ceap_c' => 'C2a',
            'esclero_concentracion' => 0.5,
            'esclero_forma' => 'Espuma',
            'esclero_volumen' => 4.0,
            'indicaciones_detalle' => ['Venotónico' => 'Perivasc 950/50', 'AINEs' => 'Ibuprofeno 400 mg'],
            'indicaciones_otros' => 'Reposo relativo 48 h',
            'evolucion' => 'Mejoría',
            'estado_general' => 'Requiere nuevas sesiones',
            'notas' => "Buena tolerancia a la sesión.\nSe cita en seis semanas.",
            'estado_registro' => $estado,
        ]);

        $historia->options()->sync(
            ClinicalOption::whereIn('categoria', ['sintomas', 'ceap_diagnostico', 'indicaciones'])
                ->whereIn('valor', ['Venotónico', 'AINEs', 'Primaria', 'Superficial'])
                ->orWhere(fn ($q) => $q->where('categoria', 'sintomas')->limit(2))
                ->pluck('id')
                ->all()
        );

        return $historia->fresh();
    }

    private function conMapeo(ClinicalHistory $historia): ClinicalHistory
    {
        Storage::disk('public')->put("mapeos-venosos/{$historia->id}/mapa.png", base64_decode(self::PNG_BASE64));

        $historia->update([
            'mapeo_venoso_path' => "mapeos-venosos/{$historia->id}/mapa.png",
            'mapeo_venoso_updated_at' => now(),
            'mapeo_venoso_datos' => [
                'version' => 1,
                'plantilla' => 'merit-mmii-6-vistas',
                'objetos' => [
                    ['tipo' => 'trazo', 'color' => 'rojo', 'trayecto' => 'safena_interna', 'puntos' => [[0.20, 0.30], [0.22, 0.45]]],
                    ['tipo' => 'marcador', 'color' => 'azul', 'marcador' => 'perforante', 'x' => 0.25, 'y' => 0.50, 'numero' => 1],
                    // Objeto del vocabulario heredado: el informe de un mapeo
                    // archivado antes de separar color, trayecto y marcador
                    // tiene que seguir imprimiéndose con su nombre.
                    ['tipo' => 'marcador', 'hallazgo' => 'cayado', 'x' => 0.70, 'y' => 0.20, 'numero' => 2],
                    ['tipo' => 'anotacion', 'texto' => 'Reflujo al Valsalva', 'x' => 0.30, 'y' => 0.60, 'numero' => 1],
                ],
            ],
        ]);

        return $historia->fresh();
    }

    private function estudio(ClinicalHistory $historia, string $estado = 'Finalizada'): DopplerReport
    {
        $segmento = fn ($nombre, $dmax, $vel, $dur, $obs) => [
            'nombre' => $nombre, 'diametro_max' => $dmax, 'velocidad' => $vel,
            'duracion' => $dur, 'diametro' => $dmax, 'observaciones' => $obs,
        ];

        $segmentos = [
            $segmento('SFJ', 8.2, 38.5, 1.2, 'Reflujo en el cayado.'),
            $segmento('GSV Muslo', 7.1, 24.0, 0.8, 'Permeable, suficiente.'),
            $segmento('GSV Pierna', 3.2, 18.0, 0.4, 'Permeable, suficiente.'),
            $segmento('Perforante de Cockett', 2.8, 12.0, 0.6, 'Insuficiente.'),
            // La quinta posición viaja vacía, que es como la manda el formulario
            $segmento(null, null, null, null, null),
        ];

        return DopplerReport::create([
            'patient_id' => $historia->patient_id,
            'clinical_history_id' => $historia->id,
            'fecha_estudio' => '2026-08-28',
            'der_profundo' => 'Eje venoso profundo permeable y compresible.',
            'der_segmentos' => $segmentos,
            'der_perforantes' => 'Perforante de Cockett insuficiente.',
            'der_trombosis' => 'Sin signos de trombosis.',
            'izq_profundo' => 'Eje venoso profundo permeable y compresible.',
            'izq_segmentos' => $segmentos,
            'izq_perforantes' => 'No se observan perforantes insuficientes.',
            'izq_trombosis' => 'Sin signos de trombosis.',
            'conclusion' => 'Insuficiencia venosa superficial bilateral a predominio derecho.',
            'estado_registro' => $estado,
            'created_by' => $this->medico()->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Historia clínica
    |--------------------------------------------------------------------------
    */

    public function test_clinical_history_is_emitted_as_pdf(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte?formato=pdf");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        $this->assertStringContainsString(
            'historia-clinica_maria-elena-portillo_2026-08-28.pdf',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_clinical_history_is_emitted_as_word(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte?formato=docx");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', self::TIPO_DOCX);

        // Un .docx es un zip: empieza por la firma PK
        $this->assertStringStartsWith('PK', $response->streamedContent());
        $this->assertStringContainsString('.docx', $response->headers->get('Content-Disposition'));
    }

    public function test_clinical_history_defaults_to_pdf(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();

        $this->actingAs($this->medico(), 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_an_unknown_format_is_rejected(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();

        $this->actingAs($this->medico(), 'sanctum')
            ->getJson("/api/v1/clinical-histories/{$historia->id}/reporte?formato=xlsx")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /**
     * Una consulta sin cerrar se puede imprimir: el médico necesita revisarla en
     * papel antes de finalizarla. Lo que no puede es parecer un informe firmado,
     * de ahí la marca de agua.
     */
    public function test_a_draft_can_still_be_emitted(): void
    {
        Storage::fake('public');
        $historia = $this->consulta('Borrador');

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte");

        $response->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    /*
    |--------------------------------------------------------------------------
    | Partes del informe
    |--------------------------------------------------------------------------
    |
    | Lo que el médico entrega es un paquete, así que el informe se compone con
    | las partes que se pidan en lugar de obligar a descargar tres archivos.
    |
    */

    /**
     * Pedir el mapeo como anexo tiene que pesar claramente más que pedir solo la
     * consulta: es la señal de que la lámina viajó dentro del documento.
     */
    public function test_the_report_only_carries_the_requested_parts(): void
    {
        Storage::fake('public');
        $historia = $this->conMapeo($this->consulta());
        $this->estudio($historia);
        $medico = $this->medico();

        $pedir = fn (string $partes) => strlen($this->actingAs($medico, 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte?partes={$partes}")
            ->streamedContent());

        $soloHistoria = $pedir('historia');
        $conMapeo = $pedir('historia,mapeo');
        $conDoppler = $pedir('historia,doppler');
        $todo = $pedir('historia,mapeo,doppler');

        $this->assertGreaterThan($soloHistoria, $conMapeo, 'El anexo del mapeo no se incluyó.');
        $this->assertGreaterThan($soloHistoria, $conDoppler, 'El anexo del Ecodöppler no se incluyó.');
        $this->assertGreaterThan($conMapeo, $todo, 'El informe completo no trae las tres partes.');
    }

    /**
     * Sin el parámetro se incluye todo lo que la consulta tenga: pedir «el
     * informe de esta consulta» a secas debe traer el expediente completo.
     */
    public function test_without_the_parts_parameter_everything_available_is_included(): void
    {
        Storage::fake('public');
        $historia = $this->conMapeo($this->consulta());
        $this->estudio($historia);
        $medico = $this->medico();

        $porDefecto = strlen($this->actingAs($medico, 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte")->streamedContent());

        $todo = strlen($this->actingAs($medico, 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte?partes=historia,mapeo,doppler")
            ->streamedContent());

        $this->assertSame($todo, $porDefecto);
    }

    /**
     * Una parte que la consulta no tiene se descarta en vez de producir un anexo
     * vacío que promete algo que no está.
     */
    public function test_parts_the_consultation_does_not_have_are_ignored(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();   // sin mapeo y sin estudio
        $medico = $this->medico();

        $pedido = strlen($this->actingAs($medico, 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte?partes=historia,mapeo,doppler")
            ->streamedContent());

        $soloHistoria = strlen($this->actingAs($medico, 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte?partes=historia")
            ->streamedContent());

        $this->assertSame($soloHistoria, $pedido);
    }

    /**
     * Pedir únicamente partes inexistentes no puede acabar en un PDF en blanco:
     * se cae a la consulta, que siempre está.
     */
    public function test_asking_only_for_missing_parts_falls_back_to_the_consultation(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte?partes=mapeo,doppler");

        $response->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    /**
     * El mapeo pedido en solitario sale como el informe suelto de siempre:
     * apaisado, porque su lámina es horizontal.
     */
    public function test_the_map_alone_is_emitted_as_its_standalone_landscape_report(): void
    {
        Storage::fake('public');
        $historia = $this->conMapeo($this->consulta());
        $medico = $this->medico();

        $compuesto = $this->actingAs($medico, 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte?partes=mapeo");

        $suelto = $this->actingAs($medico, 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/mapeo-venoso/reporte");

        $compuesto->assertStatus(200);
        $this->assertSame(
            strlen($suelto->streamedContent()),
            strlen($compuesto->streamedContent()),
            'El mapeo en solitario debería emitirse igual por las dos vías.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mapeo venoso
    |--------------------------------------------------------------------------
    */

    public function test_venous_map_report_is_emitted_as_pdf(): void
    {
        Storage::fake('public');
        $historia = $this->conMapeo($this->consulta());

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/mapeo-venoso/reporte");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        $this->assertStringContainsString('mapeo-venoso_', $response->headers->get('Content-Disposition'));
    }

    /**
     * Sin mapeo no se emite un PDF vacío: se dice que no lo hay.
     */
    public function test_venous_map_report_returns_404_when_there_is_no_map(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();

        $this->actingAs($this->medico(), 'sanctum')
            ->getJson("/api/v1/clinical-histories/{$historia->id}/mapeo-venoso/reporte")
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    /*
    |--------------------------------------------------------------------------
    | Ecodöppler
    |--------------------------------------------------------------------------
    */

    public function test_doppler_report_is_emitted_as_pdf(): void
    {
        Storage::fake('public');
        $estudio = $this->estudio($this->consulta());

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->get("/api/v1/doppler-reports/{$estudio->id}/reporte?formato=pdf");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        $this->assertStringContainsString('ecodoppler_', $response->headers->get('Content-Disposition'));
    }

    public function test_doppler_report_is_emitted_as_word(): void
    {
        Storage::fake('public');
        $estudio = $this->estudio($this->consulta());

        $response = $this->actingAs($this->medico(), 'sanctum')
            ->get("/api/v1/doppler-reports/{$estudio->id}/reporte?formato=docx");

        $response->assertStatus(200)->assertHeader('Content-Type', self::TIPO_DOCX);
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    /*
    |--------------------------------------------------------------------------
    | Acceso
    |--------------------------------------------------------------------------
    */

    public function test_reports_require_authentication(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();
        $estudio = $this->estudio($historia);

        $this->getJson("/api/v1/clinical-histories/{$historia->id}/reporte")->assertStatus(401);
        $this->getJson("/api/v1/clinical-histories/{$historia->id}/mapeo-venoso/reporte")->assertStatus(401);
        $this->getJson("/api/v1/doppler-reports/{$estudio->id}/reporte")->assertStatus(401);
    }

    /**
     * La recepcionista puede leer expedientes, así que también puede imprimirlos:
     * restringir la descarga más que la lectura solo conseguiría que viera los
     * datos en pantalla pero no pudiera entregárselos al paciente.
     */
    public function test_staff_who_can_read_a_record_can_print_it(): void
    {
        Storage::fake('public');
        $historia = $this->consulta();
        $recepcionista = User::where('rol', 'recepcionista')->first();

        $this->assertNotNull($recepcionista, 'El seeder debe crear una recepcionista.');

        $this->actingAs($recepcionista, 'sanctum')
            ->get("/api/v1/clinical-histories/{$historia->id}/reporte")
            ->assertStatus(200);
    }
}
