<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalHistory;
use App\Models\ClinicalOption;
use App\Models\DopplerReport;
use App\Models\Patient;
use App\Models\User;
use App\Support\Reportes\Estadisticos\CatalogoReportes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reportes de período.
 *
 * Se comprueban tres cosas distintas y por razones distintas:
 *
 * 1. Que los ocho salen, en los dos formatos y con el nombre correcto. Es la
 *    prueba de humo: un reporte que revienta al componerse no se descubre
 *    leyendo el código.
 * 2. Que un período sin datos emite un documento que lo dice, en vez de
 *    fallar o de sacar tablas vacías que se leen como un error.
 * 3. Que el permiso se respeta por reporte. Es lo único que no se puede
 *    verificar abriendo el PDF.
 */
class ReportePeriodoTest extends TestCase
{
    use RefreshDatabase;

    private const TIPO_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /** Rango que cubre todos los registros que siembra este test. */
    private const DESDE = '2026-09-01';

    private const HASTA = '2026-09-30';

    /**
     * Los ocho reportes del catálogo con el prefijo del archivo que emiten.
     *
     * @var array<string, string>
     */
    private const REPORTES = [
        'pacientes-atendidos' => 'pacientes-atendidos',
        'citas' => 'citas',
        'productividad-medico' => 'productividad-medico',
        'diagnosticos-ceap' => 'diagnosticos-ceap',
        'sintomas-antecedentes' => 'sintomas-antecedentes',
        'tratamientos-indicaciones' => 'tratamientos-indicaciones',
        'evolucion-seguimiento' => 'evolucion-seguimiento',
        'estudios-ecodoppler' => 'estudios-ecodoppler',
    ];

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

    private function paciente(string $nombre, int $edad = 42): Patient
    {
        return Patient::create([
            'nombre' => $nombre,
            'edad' => $edad,
            'telefono' => '+50378451200',
            'lugar_residencia' => 'Santa Ana',
            'estado_civil' => 'Casado/a',
            'estado' => 'Activo',
        ]);
    }

    /**
     * Actividad de un mes: dos pacientes, tres consultas, dos citas y un
     * estudio. Poco, pero con al menos un caso de cada cosa que las tablas
     * tienen que saber contar —incluida una consulta que empeora y otra en
     * borrador—, que es lo que distingue un reporte que funciona de uno que
     * funciona con datos redondos.
     */
    private function sembrarActividad(): void
    {
        $medico = $this->medico();
        $ana = $this->paciente('Ana García López', 38);
        $carlos = $this->paciente('Carlos Méndez Ruiz', 55);

        $primera = $this->consulta($ana, '2026-09-04', [
            'ceap_c' => 'C2a',
            'evolucion' => 'Mejoría',
            'estado_general' => 'Requiere nuevas sesiones',
            'esclero_forma' => 'Espuma',
            'esclero_concentracion' => 0.5,
            'esclero_volumen' => 4.0,
            'indicaciones_detalle' => ['Venotónico' => 'Perivasc 950/50'],
            'created_by' => $medico->id,
        ]);

        // Segunda visita del mismo paciente: es lo que activa el bloque de
        // seguimiento del reporte de evolución
        $this->consulta($ana, '2026-09-18', [
            'ceap_c' => 'C3',
            'evolucion' => 'Empeoramiento',
            'estado_general' => 'Sospecha de complicación',
            'created_by' => $medico->id,
        ]);

        $this->consulta($carlos, '2026-09-11', [
            'ceap_c' => 'C6',
            'evolucion' => 'Igual',
            'estado_general' => 'Requiere cirugía',
            'estado_registro' => 'Borrador',
            'enfermedades_otros' => 'Hipotiroidismo',
            'indicaciones_otros' => 'Reposo relativo 48 h',
            'created_by' => $medico->id,
        ]);

        $this->cita($ana, '2026-09-04 09:00:00', 'Completada');
        $this->cita($carlos, '2026-09-11 10:30:00', 'No Asistió');

        $this->estudio($primera);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function consulta(Patient $paciente, string $fecha, array $datos = []): ClinicalHistory
    {
        $historia = ClinicalHistory::create(array_merge([
            'patient_id' => $paciente->id,
            'fecha_consulta' => $fecha,
            'consulta_por' => 'Enfermedad',
            'familiar_varices' => true,
            'alergias' => 'Penicilina',
            'ubicacion_patologia' => 'BILATERAL',
            'estado_registro' => 'Finalizada',
        ], $datos));

        $historia->options()->sync(
            ClinicalOption::whereIn('valor', [
                'Pesadez', 'Calambres', 'Pantorrilla', 'Estar de pie', 'Medias compresivas',
                'Diabetes', 'Primaria', 'Superficial', 'Reflujo', 'Telangiectasias',
                'Venotónico', 'Buena respuesta',
            ])->pluck('id')->all()
        );

        return $historia->fresh();
    }

    private function cita(Patient $paciente, string $inicio, string $estado): Appointment
    {
        return Appointment::create([
            'patient_id' => $paciente->id,
            'medico_id' => $this->medico()->id,
            'created_by' => $this->medico()->id,
            'fecha_hora_inicio' => $inicio,
            'fecha_hora_fin' => date('Y-m-d H:i:s', strtotime($inicio) + 1800),
            'motivo' => 'Control de escleroterapia',
            'estado' => $estado,
            'motivo_cancelacion' => $estado === 'Cancelada' ? 'El paciente reprogramó' : null,
        ]);
    }

    private function estudio(ClinicalHistory $historia): DopplerReport
    {
        $segmentos = [
            ['nombre' => 'SFJ', 'diametro_max' => 8.2, 'velocidad' => 38.5, 'duracion' => 1.2, 'diametro' => 8.0, 'observaciones' => 'Reflujo en el cayado.'],
            ['nombre' => 'GSV Muslo', 'diametro_max' => 7.1, 'velocidad' => 24.0, 'duracion' => 0.3, 'diametro' => 7.0, 'observaciones' => 'Suficiente.'],
            ['nombre' => 'GSV Pierna', 'diametro_max' => 3.2, 'velocidad' => 18.0, 'duracion' => 0.4, 'diametro' => 3.1, 'observaciones' => 'Suficiente.'],
            // Las dos últimas posiciones viajan vacías, que es como las manda el formulario
            ['nombre' => null, 'diametro_max' => null, 'velocidad' => null, 'duracion' => null, 'diametro' => null, 'observaciones' => null],
            ['nombre' => null, 'diametro_max' => null, 'velocidad' => null, 'duracion' => null, 'diametro' => null, 'observaciones' => null],
        ];

        return DopplerReport::create([
            'patient_id' => $historia->patient_id,
            'clinical_history_id' => $historia->id,
            'fecha_estudio' => '2026-09-04',
            'der_profundo' => 'Eje venoso profundo permeable y compresible.',
            'der_segmentos' => $segmentos,
            'der_perforantes' => 'Perforante de Cockett insuficiente.',
            'der_trombosis' => 'Sin signos de trombosis.',
            'izq_profundo' => 'Eje venoso profundo permeable y compresible.',
            'izq_segmentos' => $segmentos,
            'izq_trombosis' => 'Sin signos de trombosis.',
            'conclusion' => 'Insuficiencia venosa superficial bilateral.',
            'created_by' => $this->medico()->id,
        ]);
    }

    private function url(string $clave, string $formato = 'pdf'): string
    {
        return "/api/v1/reportes/{$clave}?desde=".self::DESDE.'&hasta='.self::HASTA."&formato={$formato}";
    }

    /*
    |--------------------------------------------------------------------------
    | Emisión
    |--------------------------------------------------------------------------
    */

    public function test_every_report_in_the_catalog_is_emitted_as_pdf(): void
    {
        $this->sembrarActividad();
        $medico = $this->medico();

        foreach (self::REPORTES as $clave => $archivo) {
            $response = $this->actingAs($medico, 'sanctum')->get($this->url($clave));

            $response->assertStatus(200, "El reporte '{$clave}' no se emitió.")
                ->assertHeader('Content-Type', 'application/pdf');

            $this->assertStringStartsWith('%PDF-', $response->streamedContent(), "El reporte '{$clave}' no devolvió un PDF.");
            $this->assertStringContainsString(
                "{$archivo}_2026-09-30.pdf",
                $response->headers->get('Content-Disposition'),
                "El reporte '{$clave}' se descargó con otro nombre."
            );
        }
    }

    public function test_every_report_in_the_catalog_is_emitted_as_word(): void
    {
        $this->sembrarActividad();
        $medico = $this->medico();

        foreach (array_keys(self::REPORTES) as $clave) {
            $response = $this->actingAs($medico, 'sanctum')->get($this->url($clave, 'docx'));

            $response->assertStatus(200, "El reporte '{$clave}' no se emitió en Word.")
                ->assertHeader('Content-Type', self::TIPO_DOCX);

            // Un .docx es un zip: empieza por la firma PK
            $this->assertStringStartsWith('PK', $response->streamedContent(), "El reporte '{$clave}' no devolvió un .docx.");
        }
    }

    /**
     * Un período sin actividad tiene que emitir igual: el documento que dice
     * «no hubo registros» es una respuesta, y un 500 no.
     */
    public function test_an_empty_period_still_produces_a_document(): void
    {
        $medico = $this->medico();

        foreach (array_keys(self::REPORTES) as $clave) {
            $response = $this->actingAs($medico, 'sanctum')
                ->get("/api/v1/reportes/{$clave}?desde=2020-01-01&hasta=2020-01-31");

            $response->assertStatus(200, "El reporte '{$clave}' falló con un período vacío.");
            $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        }
    }

    /**
     * Sin fechas se toma el mes en curso en lugar de fallar: es el reporte que
     * se pide nueve de cada diez veces.
     */
    public function test_without_dates_the_current_month_is_used(): void
    {
        $this->actingAs($this->medico(), 'sanctum')
            ->get('/api/v1/reportes/citas')
            ->assertStatus(200);
    }

    /**
     * Un rango al revés se endereza: el error está en el formulario, no en los
     * datos, y devolver un reporte vacío ocultaría la causa.
     */
    public function test_an_inverted_range_is_straightened(): void
    {
        $this->sembrarActividad();
        $medico = $this->medico();

        $derecho = $this->actingAs($medico, 'sanctum')
            ->get('/api/v1/reportes/citas?desde=2026-09-01&hasta=2026-09-30')
            ->streamedContent();

        $invertido = $this->actingAs($medico, 'sanctum')
            ->get('/api/v1/reportes/citas?desde=2026-09-30&hasta=2026-09-01')
            ->streamedContent();

        $this->assertSame(strlen($derecho), strlen($invertido));
    }

    public function test_an_unknown_format_is_rejected(): void
    {
        $this->actingAs($this->medico(), 'sanctum')
            ->getJson($this->url('citas', 'xlsx'))
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_an_unknown_report_returns_404(): void
    {
        $this->actingAs($this->medico(), 'sanctum')
            ->getJson('/api/v1/reportes/inventado')
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    /**
     * El filtro por paciente tiene que recortar de verdad. Se compara contra el
     * mismo reporte sin filtrar: si el parámetro se ignorara, los dos
     * documentos saldrían idénticos.
     */
    public function test_filtering_by_patient_narrows_the_report(): void
    {
        $this->sembrarActividad();
        $medico = $this->medico();
        $ana = Patient::where('nombre', 'Ana García López')->first();

        $todos = $this->actingAs($medico, 'sanctum')
            ->get($this->url('pacientes-atendidos'))->streamedContent();

        $soloAna = $this->actingAs($medico, 'sanctum')
            ->get($this->url('pacientes-atendidos')."&patient_id={$ana->id}")->streamedContent();

        $this->assertNotSame(strlen($todos), strlen($soloAna));
    }

    /**
     * Un filtro que el reporte no declara se ignora en vez de aplicarse a
     * ciegas: `medico_id` no significa nada en el reporte de síntomas.
     */
    public function test_a_filter_the_report_does_not_accept_is_ignored(): void
    {
        $this->sembrarActividad();
        $medico = $this->medico();

        $sinFiltro = $this->actingAs($medico, 'sanctum')
            ->get($this->url('sintomas-antecedentes'))->streamedContent();

        $conFiltro = $this->actingAs($medico, 'sanctum')
            ->get($this->url('sintomas-antecedentes')."&medico_id={$medico->id}")->streamedContent();

        $this->assertSame(strlen($sinFiltro), strlen($conFiltro));
    }

    /*
    |--------------------------------------------------------------------------
    | Catálogo y permisos
    |--------------------------------------------------------------------------
    */

    public function test_the_catalog_lists_what_the_user_may_emit(): void
    {
        $this->actingAs($this->medico(), 'sanctum')
            ->getJson('/api/v1/reportes')
            ->assertStatus(200)
            ->assertJsonCount(count(self::REPORTES), 'data')
            ->assertJsonStructure(['data' => [['clave', 'titulo', 'descripcion', 'filtros', 'formatos']]]);
    }

    /**
     * La recepcionista lleva la agenda, así que ve los reportes de agenda; la
     * epidemiología de la consulta y la producción del personal, no.
     */
    public function test_the_catalog_is_trimmed_for_a_receptionist(): void
    {
        $recepcionista = User::where('rol', 'recepcionista')->first();
        $this->assertNotNull($recepcionista, 'El seeder debe crear una recepcionista.');

        $claves = collect($this->actingAs($recepcionista, 'sanctum')
            ->getJson('/api/v1/reportes')
            ->assertStatus(200)
            ->json('data'))
            ->pluck('clave');

        $this->assertEqualsCanonicalizing(['pacientes-atendidos', 'citas'], $claves->all());
    }

    public function test_a_receptionist_cannot_emit_a_restricted_report(): void
    {
        $recepcionista = User::where('rol', 'recepcionista')->first();

        $this->actingAs($recepcionista, 'sanctum')
            ->getJson($this->url('productividad-medico'))
            ->assertStatus(403)
            ->assertJson(['success' => false]);

        $this->actingAs($recepcionista, 'sanctum')
            ->get($this->url('citas'))
            ->assertStatus(200);
    }

    public function test_reports_require_authentication(): void
    {
        $this->getJson('/api/v1/reportes')->assertStatus(401);
        $this->getJson($this->url('citas'))->assertStatus(401);
    }

    /**
     * El catálogo y las claves del test se declaran por separado a propósito: si
     * alguien añade un reporte y no lo prueba, esto lo dice.
     */
    public function test_the_catalog_and_this_test_cover_the_same_reports(): void
    {
        foreach (array_keys(self::REPORTES) as $clave) {
            $this->assertTrue(CatalogoReportes::existe($clave), "El catálogo no declara '{$clave}'.");
        }

        $delCatalogo = collect(CatalogoReportes::descriptores(User::where('rol', 'administrador')->first()))
            ->pluck('clave')
            ->all();

        $this->assertEqualsCanonicalizing(array_keys(self::REPORTES), $delCatalogo);
    }
}
