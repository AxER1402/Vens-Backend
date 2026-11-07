<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\BlockedDay;
use App\Models\ClinicalHistory;
use App\Models\ClinicalOption;
use App\Models\DopplerReport;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Support\Ajustes\Ajustes;
use App\Support\Facturacion\Totales;
use Database\Seeders\Demo\AjusteDemo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Datos ficticios para el entorno de demostración (docker-compose.demo.yml).
 *
 * Ninguna persona de aquí existe: nombres, teléfonos y hallazgos son inventados
 * para poder enseñar el sistema sin exponer a los pacientes de la clínica.
 *
 * Cubre las semanas del 19 de octubre al 6 de noviembre de 2026, con el ritmo
 * de una consulta pequeña: uno o dos pacientes al día, días sin consulta, el
 * feriado del 20 de octubre y el cierre de Todos los Santos (del viernes 30 al
 * lunes 2) bloqueados en la agenda, y tres citas agendadas para la semana del 9.
 *
 * No está en DatabaseSeeder a propósito, y se niega a correr sobre una base que
 * ya tenga pacientes o en un servidor cuyo APP_URL no sea el de la demo:
 *
 *   php artisan db:seed --class=DemoSeeder --force
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Días en que la clínica no atiende: se bloquean en la agenda y ni se
     * agenda ni se registra nada en ellos.
     *
     * @var array<int, array{inicio: string, fin: string, motivo: string, tipo: string, registrado: string}>
     */
    public const BLOQUEOS = [
        [
            'inicio' => '2026-10-20', 'fin' => '2026-10-20',
            'motivo' => 'Día de la Revolución', 'tipo' => 'Feriado',
            'registrado' => '2026-10-13 18:55:00',
        ],
        [
            'inicio' => '2026-10-30', 'fin' => '2026-11-02',
            'motivo' => 'Día de Todos los Santos: la clínica cierra del viernes 30 al lunes 2', 'tipo' => 'Feriado',
            'registrado' => '2026-10-23 12:18:00',
        ],
    ];

    /**
     * Nombres de las cuentas en la demo. Se ponen aquí y no en UserSeeder,
     * que también siembra producción. La bitácora sembrada los toma de aquí.
     *
     * @var array<string, string>
     */
    private const NOMBRES = [
        'admin@vens.com' => 'Alexander Blanco',
        'medico@vens.com' => 'Dra. Yojana Vicente',
        'recepcion@vens.com' => 'Secretaria',
    ];

    /** Horario de atención: de lunes a viernes, de 8:00 a 17:00. */
    private const HORARIO = [
        'lunes' => ['activo' => true, 'abre' => '08:00', 'cierra' => '17:00'],
        'martes' => ['activo' => true, 'abre' => '08:00', 'cierra' => '17:00'],
        'miercoles' => ['activo' => true, 'abre' => '08:00', 'cierra' => '17:00'],
        'jueves' => ['activo' => true, 'abre' => '08:00', 'cierra' => '17:00'],
        'viernes' => ['activo' => true, 'abre' => '08:00', 'cierra' => '17:00'],
        'sabado' => ['activo' => false, 'abre' => null, 'cierra' => null],
        'domingo' => ['activo' => false, 'abre' => null, 'cierra' => null],
    ];

    private User $medico;

    private User $recepcion;

    /** @var array<string, Service> */
    private array $servicios = [];

    /** La hora real: nada se fecha después de ella aunque la cita sea futura. */
    private Carbon $ahora;

    /** Fin de la cita anterior del paciente que se está sembrando. */
    private ?Carbon $citaAnterior = null;

    public function run(): void
    {
        $this->comprobarQueEsLaDemo();
        $this->ahora = Carbon::now();

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ClinicalOptionSeeder::class,
            AjusteSeeder::class,
        ]);

        foreach (self::NOMBRES as $correo => $nombre) {
            User::where('email', $correo)->update(['name' => $nombre]);
        }

        $this->medico = User::where('rol', 'medico')->firstOrFail();
        $this->recepcion = User::where('rol', 'recepcionista')->firstOrFail();

        $this->sembrarAgenda();
        $this->sembrarServicios();

        foreach ($this->pacientes() as $datos) {
            $this->sembrarPaciente($datos);
        }

        Carbon::setTestNow();

        $this->numerarRecibosPorFecha();

        // Una consulta por paciente, mapeos venosos y bitácora: lo mismo que
        // `php artisan demo:ajustar` hace sobre una demo ya sembrada.
        AjusteDemo::aplicar(AjusteDemo::planear());
    }

    /**
     * Los recibos se crean paciente por paciente, pero el correlativo de un
     * mostrador avanza con el calendario: se renumeran en el orden en que se
     * cobraron. El primer pase los aparta para no chocar con el índice único.
     */
    private function numerarRecibosPorFecha(): void
    {
        $recibos = Invoice::orderBy('created_at')->orderBy('id')->get();

        foreach ($recibos as $recibo) {
            $recibo->timestamps = false;
            $recibo->update(['numero' => $recibo->numero + 100000]);
        }

        foreach ($recibos->values() as $posicion => $recibo) {
            $recibo->update(['numero' => $posicion + 1]);
        }
    }

    /**
     * Los registros se crean con la fecha en que habrían ocurrido, para que los
     * listados y la auditoría cuenten una historia coherente.
     */
    private function fecharEn(Carbon $momento): void
    {
        Carbon::setTestNow($momento->min($this->ahora));
    }

    private function comprobarQueEsLaDemo(): void
    {
        if (app()->environment('production') && ! str_contains((string) config('app.url'), 'demo')) {
            throw new RuntimeException('DemoSeeder solo corre en el entorno de demostración (APP_URL con "demo").');
        }

        if (Patient::exists()) {
            throw new RuntimeException('La base ya tiene pacientes: DemoSeeder solo siembra una base vacía (make demo-reset).');
        }
    }

    private function sembrarServicios(): void
    {
        $catalogo = [
            'consulta' => ['Consulta de flebología', 'Evaluación clínica de miembros inferiores', 350],
            'control' => ['Consulta de control', 'Seguimiento de tratamiento', 250],
            'doppler' => ['Ecodöppler venoso de miembros inferiores', 'Estudio bilateral', 550],
            'esclero' => ['Sesión de escleroterapia', 'Por sesión, incluye material', 750],
            'medias' => ['Medias de compresión 20-30 mmHg', 'Par, hasta la rodilla', 420],
        ];

        foreach ($catalogo as $clave => [$nombre, $descripcion, $precio]) {
            $this->servicios[$clave] = Service::create([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'activo' => true,
            ]);
        }
    }

    /**
     * El horario de atención y los días bloqueados los pone la médica la
     * primera semana, y el cierre de Todos los Santos lo agrega días antes.
     */
    private function sembrarAgenda(): void
    {
        $this->fecharEn(Carbon::parse('2026-10-13 18:50:00'));
        Ajustes::guardar(['agenda.horario' => self::HORARIO, 'agenda.duracion_cita' => 30]);

        foreach (self::BLOQUEOS as $bloqueo) {
            $this->fecharEn(Carbon::parse($bloqueo['registrado']));
            BlockedDay::create([
                'created_by' => $this->medico->id,
                'fecha_inicio' => $bloqueo['inicio'],
                'fecha_fin' => $bloqueo['fin'],
                'motivo' => $bloqueo['motivo'],
                'tipo' => $bloqueo['tipo'],
            ]);
        }
    }

    /** ¿La clínica atiende ese día? Ni fines de semana ni días bloqueados. */
    public static function diaDeConsulta(Carbon $dia): bool
    {
        if ($dia->isWeekend()) {
            return false;
        }

        foreach (self::BLOQUEOS as $bloqueo) {
            if ($dia->between(Carbon::parse($bloqueo['inicio'])->startOfDay(), Carbon::parse($bloqueo['fin'])->endOfDay())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cuándo se agendó la cita desde recepción: unos días antes, en un día que
     * la clínica abrió, y nunca antes de la visita anterior del mismo paciente
     * (el control se agenda al salir de la consulta que lo pidió).
     */
    private function agendadaPara(Carbon $inicio): Carbon
    {
        $dia = $inicio->copy()->subDays(4)->setTime(10, 15);
        while (! self::diaDeConsulta($dia)) {
            $dia->subDay();
        }

        if ($this->citaAnterior && $dia->lt($this->citaAnterior)) {
            $dia = $this->citaAnterior->copy()->addMinutes(10);
        }

        return $dia;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function sembrarPaciente(array $datos): void
    {
        $citas = $datos['citas'];
        unset($datos['citas']);

        // Recepción registra al paciente cuando le agenda su primera cita
        $this->citaAnterior = null;
        $this->fecharEn($this->agendadaPara(Carbon::parse($citas[0]['inicio']))->subMinutes(5));
        $paciente = Patient::create($datos + ['activo' => true]);

        foreach ($citas as $cita) {
            $this->sembrarCita($paciente, $cita);
        }
    }

    /**
     * @param  array<string, mixed>  $cita
     */
    private function sembrarCita(Patient $paciente, array $cita): void
    {
        $inicio = Carbon::parse($cita['inicio']);

        // La cita se agenda unos días antes desde recepción
        $this->fecharEn($this->agendadaPara($inicio));
        // Una cita que no se dio (se movió o se canceló) no marca cuándo se
        // agenda la siguiente: esa se pide por teléfono antes de su hora.
        if (! in_array($cita['estado'], ['Reagendada', 'Cancelada'], true)) {
            $this->citaAnterior = $inicio->copy()->addMinutes($cita['minutos'] ?? 30);
        }
        $agendada = Appointment::create([
            'patient_id' => $paciente->id,
            'medico_id' => $this->medico->id,
            'created_by' => $this->recepcion->id,
            'fecha_hora_inicio' => $inicio,
            'fecha_hora_fin' => $inicio->copy()->addMinutes($cita['minutos'] ?? 30),
            'motivo' => $cita['motivo'],
            'estado' => $cita['estado'],
            'motivo_cancelacion' => $cita['motivo_cancelacion'] ?? null,
            'notas' => $cita['notas'] ?? null,
        ]);

        if (! isset($cita['historia'])) {
            return;
        }

        Carbon::setTestNow($inicio->copy()->addMinutes(25));
        $historia = $this->sembrarHistoria($paciente, $agendada, $cita['historia']);

        if (isset($cita['doppler'])) {
            DopplerReport::create($cita['doppler'] + [
                'patient_id' => $paciente->id,
                'clinical_history_id' => $historia->id,
                'fecha_estudio' => $inicio->toDateString(),
                'estado_registro' => 'Finalizada',
                'activo' => true,
                'created_by' => $this->medico->id,
                'updated_by' => $this->medico->id,
            ]);
        }

        Carbon::setTestNow($inicio->copy()->addMinutes(($cita['minutos'] ?? 30) + 5));
        $this->sembrarRecibo($paciente, $historia, $cita['cobro']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function sembrarHistoria(Patient $paciente, Appointment $cita, array $datos): ClinicalHistory
    {
        $selecciones = $datos['selecciones'] ?? [];
        unset($datos['selecciones']);

        $historia = ClinicalHistory::create($datos + [
            'patient_id' => $paciente->id,
            'appointment_id' => $cita->id,
            'fecha_consulta' => $cita->fecha_hora_inicio->toDateString(),
            'estado_registro' => 'Finalizada',
            'activo' => true,
            'created_by' => $this->medico->id,
            'updated_by' => $this->medico->id,
        ]);

        $ids = [];
        foreach ($selecciones as $categoria => $valores) {
            $ids = array_merge($ids, ClinicalOption::where('categoria', $categoria)
                ->whereIn('valor', $valores)
                ->pluck('id')
                ->all());
        }
        $historia->options()->sync($ids);

        return $historia;
    }

    /**
     * @param  array<string, mixed>  $cobro
     */
    private function sembrarRecibo(Patient $paciente, ClinicalHistory $historia, array $cobro): void
    {
        $renglones = array_map(fn (string $clave) => [
            'tipo' => $clave === 'medias' ? 'B' : 'S',
            'descripcion' => $this->servicios[$clave]->nombre,
            'cantidad' => 1,
            'precio_unitario' => $this->servicios[$clave]->precio,
        ], $cobro['servicios']);

        // Un cobro mal emitido: recepción lo anula y lo vuelve a emitir con el
        // dato correcto, como se hace en el mostrador. Queda en la bitácora.
        if (isset($cobro['corrige'])) {
            $equivocado = $this->emitirRecibo($paciente, $historia, $renglones, Invoice::NIT_CONSUMIDOR_FINAL, $cobro);

            Carbon::setTestNow(now()->addMinutes(2));
            $equivocado->update(['estado' => 'Anulada', 'motivo_anulacion' => $cobro['corrige']]);

            Carbon::setTestNow(now()->addMinutes(1));
        }

        $this->emitirRecibo($paciente, $historia, $renglones, $cobro['nit'] ?? Invoice::NIT_CONSUMIDOR_FINAL, $cobro);
    }

    /**
     * @param  array<int, array<string, mixed>>  $renglones
     * @param  array<string, mixed>  $cobro
     */
    private function emitirRecibo(Patient $paciente, ClinicalHistory $historia, array $renglones, string $nit, array $cobro): Invoice
    {
        $iva = (float) config('facturacion.iva_porcentaje', 12);
        $cuentas = Totales::calcular($renglones, $iva);
        $serie = (string) config('facturacion.serie', 'A');

        $recibo = Invoice::create([
            'patient_id' => $paciente->id,
            'clinical_history_id' => $historia->id,
            'created_by' => $this->recepcion->id,
            'tipo' => Invoice::TIPO_RECIBO,
            'serie' => $serie,
            'numero' => Invoice::siguienteNumero($serie),
            'fecha_emision' => now()->toDateString(),
            'nit_receptor' => $nit,
            'nombre_receptor' => $paciente->nombre,
            'moneda' => (string) config('facturacion.moneda', 'GTQ'),
            'subtotal' => $cuentas['subtotal'],
            'descuento' => $cuentas['descuento'],
            'total' => $cuentas['total'],
            'iva_porcentaje' => $iva,
            'iva_monto' => $cuentas['iva_monto'],
            'metodo_pago' => $cobro['pago'] ?? 'Efectivo',
        ]);

        $recibo->items()->createMany($cuentas['renglones']);

        return $recibo;
    }

    /**
     * Segmentos de un lado del Doppler: los tres fijos y dos que nombra el médico.
     * Cada medida viene como [diámetro máx., velocidad, duración del reflujo, diámetro].
     *
     * @param  array<int, array{0: string|null, 1: array<int, float|null>, 2: string|null}>  $segmentos
     * @return array<int, array<string, mixed>>
     */
    private function segmentos(array $segmentos): array
    {
        return array_map(fn (array $s) => [
            'nombre' => $s[0],
            'diametro_max' => $s[1][0],
            'velocidad' => $s[1][1],
            'duracion' => $s[1][2],
            'diametro' => $s[1][3],
            'observaciones' => $s[2],
        ], $segmentos);
    }

    /**
     * Los ocho pacientes atendidos entre el 19 de octubre y el 6 de noviembre de
     * 2026, más una paciente nueva con su primera cita la semana siguiente:
     * siete mujeres y dos hombres.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pacientes(): array
    {
        $profundoNormal = 'Sistema venoso profundo permeable, compresible, con flujo fásico y sin reflujo.';

        return [
            [
                'nombre' => 'María Fernanda López Cifuentes',
                'edad' => 42,
                'telefono' => '55218764',
                'lugar_residencia' => 'Santa Cruz del Quiché, Quiché',
                'estado_civil' => 'Casado/a',
                'religion' => 'Católica',
                'estado' => 'Seguimiento',
                'citas' => [
                    [
                        'inicio' => '2026-10-19 08:30:00',
                        'minutos' => 45,
                        'motivo' => 'Primera consulta — várices y pesadez en ambas piernas',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'familiar_varices' => true,
                            'alergias' => 'Ninguna conocida',
                            'cirugias' => 'Cesárea (2014)',
                            'gestas' => 2, 'partos' => 1, 'cesareas' => 1, 'abortos' => 0, 'hijos_vivos' => 2, 'hijos_muertos' => 0,
                            'ultima_menstruacion' => '2026-10-05',
                            'presion_arterial' => '118/76', 'frecuencia_cardiaca' => 74, 'frecuencia_respiratoria' => 16,
                            'temperatura' => 36.6, 'peso' => 68.5, 'perimetro_tobillo' => 23.5, 'perimetro_pantorrilla' => 36.0,
                            'ubicacion_patologia' => 'BILATERAL',
                            'ceap_c' => 'C2',
                            'indicaciones_detalle' => ['Venotónico' => 'Diosmina/Hesperidina 500 mg, 1 tableta cada 12 horas por 3 meses', 'Medias Compresivas' => 'Hasta la rodilla 20-30 mmHg, uso diurno'],
                            'notas' => 'Várices tronculares en cara interna de ambos muslos, más marcadas en MII. Se solicita Ecodöppler y se plantea escleroterapia con espuma.',
                            'selecciones' => [
                                'zonas_pierna' => ['Muslo', 'Pantorrilla'],
                                'sintomas' => ['Pesadez', 'Cansancio', 'Calambres'],
                                'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                                'sintomas_disminuyen' => ['Elevación de las piernas', 'Reposo'],
                                'ceap_diagnostico' => ['Primaria', 'Superficial', 'Reflujo'],
                                'tx_zonas' => ['Varicosas trunculares', 'Reticulares'],
                                'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                            ],
                        ],
                        'doppler' => [
                            'der_profundo' => $profundoNormal,
                            'der_segmentos' => $this->segmentos([
                                ['SFJ', [7.8, 18.0, 0.9, 6.5], 'Reflujo leve en unión safenofemoral'],
                                ['GSV Muslo', [5.9, 12.0, 0.7, 5.1], 'Reflujo segmentario'],
                                ['GSV Pierna', [3.4, null, null, 3.1], 'Sin reflujo'],
                                ['SSV', [3.0, null, null, 2.8], 'Competente'],
                                ['Tributaria anterior', [4.1, null, 0.6, 3.8], 'Dilatada'],
                            ]),
                            'der_perforantes' => 'Sin perforantes incompetentes',
                            'der_trombosis' => 'Sin signos de trombosis',
                            'izq_profundo' => $profundoNormal,
                            'izq_segmentos' => $this->segmentos([
                                ['SFJ', [8.6, 24.0, 1.8, 7.2], 'Reflujo significativo en unión safenofemoral'],
                                ['GSV Muslo', [6.8, 16.0, 1.5, 6.0], 'Reflujo en todo el trayecto'],
                                ['GSV Pierna', [4.2, 9.0, 0.8, 3.9], 'Reflujo hasta tercio medio'],
                                ['SSV', [3.2, null, null, 2.9], 'Competente'],
                                ['Tributaria posteromedial', [5.0, null, 1.1, 4.6], 'Variz tributaria'],
                            ]),
                            'izq_perforantes' => 'Perforante de Dodd incompetente, 3.5 mm',
                            'izq_trombosis' => 'Sin signos de trombosis',
                            'conclusion' => 'Insuficiencia de vena safena mayor bilateral, de predominio izquierdo, con reflujo en unión safenofemoral. Sistema venoso profundo competente. Sin trombosis.',
                        ],
                        'cobro' => ['servicios' => ['consulta', 'doppler'], 'pago' => 'Tarjeta'],
                    ],
                    [
                        'inicio' => '2026-10-26 09:00:00',
                        'minutos' => 45,
                        'motivo' => 'Primera sesión de escleroterapia con espuma — MII',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'presion_arterial' => '116/74', 'frecuencia_cardiaca' => 72, 'temperatura' => 36.5, 'peso' => 68.2,
                            'perimetro_tobillo' => 23.0, 'perimetro_pantorrilla' => 35.5,
                            'ubicacion_patologia' => 'MII',
                            'ceap_c' => 'C2',
                            'esclero_concentracion' => 1.00, 'esclero_forma' => 'Espuma', 'esclero_volumen' => 4.00,
                            'indicaciones_detalle' => ['Medias Compresivas' => 'Uso continuo 72 horas y luego diurno por 3 semanas'],
                            'evolucion' => 'Mejoría',
                            'estado_general' => 'Requiere nuevas sesiones',
                            'notas' => 'Polidocanol al 1 % en espuma sobre tributaria posteromedial y GSV de muslo. Tolera bien el procedimiento. Control en una semana.',
                            'selecciones' => [
                                'tx_zonas' => ['Varicosas trunculares'],
                                'indicaciones' => ['Medias Compresivas'],
                                'observaciones' => ['Sin complicaciones', 'Buena respuesta'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['esclero', 'medias'], 'pago' => 'Tarjeta'],
                    ],
                    [
                        'inicio' => '2026-11-10 09:00:00',
                        'motivo' => 'Control post escleroterapia',
                        'estado' => 'Programada',
                    ],
                ],
            ],
            [
                'nombre' => 'Ana Lucía Herrera Morales',
                'edad' => 35,
                'telefono' => '42917355',
                'lugar_residencia' => 'Santa Cruz del Quiché, Quiché',
                'estado_civil' => 'Soltero/a',
                'religion' => 'Evangélica',
                'estado' => 'Seguimiento',
                'citas' => [
                    [
                        'inicio' => '2026-10-21 10:00:00',
                        'motivo' => 'Valoración de arañitas vasculares en muslos',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Estética',
                            'familiar_varices' => true,
                            'alergias' => 'Penicilina',
                            'gestas' => 0, 'partos' => 0, 'cesareas' => 0, 'abortos' => 0,
                            'ultima_menstruacion' => '2026-10-08',
                            'hormonas' => 'Anticonceptivos orales',
                            'presion_arterial' => '110/70', 'frecuencia_cardiaca' => 68, 'frecuencia_respiratoria' => 14,
                            'temperatura' => 36.4, 'peso' => 59.0,
                            'ubicacion_patologia' => 'BILATERAL',
                            'ceap_c' => 'C1',
                            'indicaciones_detalle' => ['Crema' => 'Crema con vitamina K, aplicar por la noche'],
                            'notas' => 'Telangiectasias y venas reticulares en cara lateral de ambos muslos. Sin datos de insuficiencia troncular. Candidata a escleroterapia líquida.',
                            'selecciones' => [
                                'zonas_pierna' => ['Muslo'],
                                'sintomas' => ['Asintomática'],
                                'ceap_diagnostico' => ['Primaria', 'Superficial'],
                                'tx_zonas' => ['Telangiectasias', 'Reticulares'],
                                'indicaciones' => ['Crema'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['consulta'], 'pago' => 'Transferencia', 'nit' => '8452197K'],
                    ],
                    [
                        'inicio' => '2026-11-03 10:00:00',
                        'minutos' => 45,
                        'motivo' => 'Sesión de escleroterapia líquida',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Estética',
                            'presion_arterial' => '112/72', 'frecuencia_cardiaca' => 70, 'temperatura' => 36.5, 'peso' => 59.0,
                            'ubicacion_patologia' => 'BILATERAL',
                            'ceap_c' => 'C1',
                            'esclero_concentracion' => 0.50, 'esclero_forma' => 'Líquida', 'esclero_volumen' => 6.00,
                            'indicaciones_detalle' => ['Medias Compresivas' => 'Uso diurno por 2 semanas', 'Crema' => 'Árnica tópica en zonas tratadas'],
                            'evolucion' => 'Mejoría',
                            'estado_general' => 'Requiere nuevas sesiones',
                            'notas' => 'Polidocanol al 0.5 % en telangiectasias de ambos muslos. Eritema leve inmediato que cede. Se recomienda evitar sol y ejercicio intenso 48 horas.',
                            'selecciones' => [
                                'tx_zonas' => ['Telangiectasias'],
                                'indicaciones' => ['Medias Compresivas', 'Crema'],
                                'observaciones' => ['Eritema leve', 'Buena respuesta'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['esclero'], 'pago' => 'Transferencia', 'nit' => '8452197K', 'corrige' => 'Se emitió a consumidor final; la paciente pidió el recibo con su NIT.'],
                    ],
                ],
            ],
            [
                'nombre' => 'Rosa Elvira Chávez de Pineda',
                'edad' => 58,
                'telefono' => '57340219',
                'lugar_residencia' => 'Chichicastenango, Quiché',
                'estado_civil' => 'Casado/a',
                'religion' => 'Católica',
                'estado' => 'Seguimiento',
                'citas' => [
                    [
                        'inicio' => '2026-10-22 09:00:00',
                        'minutos' => 45,
                        'motivo' => 'Hinchazón y manchas en pierna izquierda',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'familiar_varices' => true,
                            'alergias' => 'Ninguna conocida',
                            'cirugias' => 'Histerectomía (2015)',
                            'gestas' => 4, 'partos' => 4, 'cesareas' => 0, 'abortos' => 0, 'hijos_vivos' => 4, 'hijos_muertos' => 0,
                            'enfermedades_otros' => 'Hipotiroidismo en tratamiento',
                            'presion_arterial' => '138/86', 'frecuencia_cardiaca' => 80, 'frecuencia_respiratoria' => 17,
                            'temperatura' => 36.7, 'peso' => 81.3, 'perimetro_tobillo' => 27.5, 'perimetro_pantorrilla' => 40.0,
                            'ubicacion_patologia' => 'MII',
                            'ceap_c' => 'C4a',
                            'indicaciones_detalle' => ['Venotónico' => 'Diosmina/Hesperidina 1000 mg, 1 tableta al día por 6 meses', 'Medias Compresivas' => 'Hasta la rodilla 30-40 mmHg', 'Crema' => 'Emoliente con urea 10 % dos veces al día'],
                            'notas' => 'Edema blando en MII que deja fóvea, dermatitis ocre en tercio distal de pierna. Pulsos presentes. Se realiza Ecodöppler en la misma consulta.',
                            'selecciones' => [
                                'zonas_pierna' => ['Pantorrilla', 'Tobillo'],
                                'sintomas' => ['Hinchazón', 'Pesadez', 'Manchas en la piel', 'Picazón'],
                                'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                                'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                                'enfermedades' => ['Alta o baja presión', 'Otros'],
                                'ceap_diagnostico' => ['Primaria', 'Superficial', 'Perforantes', 'Reflujo'],
                                'tx_zonas' => ['Varicosas trunculares', 'Perforantes'],
                                'indicaciones' => ['Venotónico', 'Medias Compresivas', 'Crema'],
                            ],
                        ],
                        'doppler' => [
                            'der_profundo' => $profundoNormal,
                            'der_segmentos' => $this->segmentos([
                                ['SFJ', [6.9, null, null, 6.1], 'Competente'],
                                ['GSV Muslo', [4.8, null, null, 4.4], 'Competente'],
                                ['GSV Pierna', [3.5, null, null, 3.2], 'Competente'],
                                ['SSV', [3.1, null, null, 2.7], 'Competente'],
                                [null, [null, null, null, null], null],
                            ]),
                            'der_perforantes' => 'Sin perforantes incompetentes',
                            'der_trombosis' => 'Sin signos de trombosis',
                            'izq_profundo' => 'Sistema venoso profundo permeable y compresible. Reflujo breve en vena poplítea (0.6 s), no significativo.',
                            'izq_segmentos' => $this->segmentos([
                                ['SFJ', [9.4, 26.0, 2.4, 8.1], 'Reflujo significativo'],
                                ['GSV Muslo', [7.2, 18.0, 2.0, 6.6], 'Reflujo en todo el trayecto'],
                                ['GSV Pierna', [5.6, 12.0, 1.6, 5.0], 'Reflujo hasta maléolo'],
                                ['SSV', [4.0, 8.0, 0.9, 3.6], 'Reflujo leve'],
                                ['Perforante de Cockett', [4.3, null, 0.8, 4.0], 'Incompetente'],
                            ]),
                            'izq_perforantes' => 'Perforante de Cockett II incompetente, 4.3 mm, en tercio distal medial',
                            'izq_trombosis' => 'Sin signos de trombosis',
                            'conclusion' => 'Insuficiencia venosa superficial de MII con reflujo safenofemoral y de safena mayor en todo su trayecto, perforante de Cockett incompetente. MID sin reflujo patológico. Sin trombosis venosa profunda.',
                        ],
                        'cobro' => ['servicios' => ['consulta', 'doppler', 'medias']],
                    ],
                    [
                        'inicio' => '2026-10-29 09:00:00',
                        'motivo' => 'Control de edema y respuesta a compresión',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'presion_arterial' => '132/84', 'frecuencia_cardiaca' => 78, 'temperatura' => 36.6, 'peso' => 80.9,
                            'perimetro_tobillo' => 26.5, 'perimetro_pantorrilla' => 39.0,
                            'ubicacion_patologia' => 'MII',
                            'ceap_c' => 'C4a',
                            'evolucion' => 'Mejoría',
                            'estado_general' => 'Requiere nuevas sesiones',
                            'notas' => 'Disminución de 1 cm del perímetro de tobillo con uso de medias. Pendiente programar escleroterapia de perforante y safena.',
                            'estado_registro' => 'Borrador',
                            'selecciones' => [
                                'sintomas' => ['Hinchazón', 'Pesadez'],
                                'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['control']],
                    ],
                ],
            ],
            [
                'nombre' => 'Carlos Enrique Méndez Rodas',
                'edad' => 51,
                'telefono' => '30482617',
                'lugar_residencia' => 'Santa María Nebaj, Quiché',
                'estado_civil' => 'Casado/a',
                'religion' => 'Católica',
                'estado' => 'Activo',
                'citas' => [
                    [
                        'inicio' => '2026-10-21 11:00:00',
                        'minutos' => 45,
                        'motivo' => 'Várices en pierna derecha y calambres nocturnos',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'familiar_varices' => false,
                            'alergias' => 'Ninguna conocida',
                            'cirugias' => 'Apendicectomía (1998)',
                            'presion_arterial' => '126/82', 'frecuencia_cardiaca' => 76, 'frecuencia_respiratoria' => 16,
                            'temperatura' => 36.5, 'peso' => 86.0, 'perimetro_tobillo' => 25.0, 'perimetro_pantorrilla' => 39.5,
                            'ubicacion_patologia' => 'MID',
                            'ceap_c' => 'C2',
                            'indicaciones_detalle' => ['Venotónico' => 'Diosmina/Hesperidina 500 mg cada 12 horas', 'Medias Compresivas' => 'Hasta la rodilla 20-30 mmHg en jornada laboral'],
                            'notas' => 'Trabaja 10 horas de pie (supervisor de bodega). Várices tronculares en cara posterior de pierna derecha.',
                            'selecciones' => [
                                'zonas_pierna' => ['Pantorrilla'],
                                'sintomas' => ['Calambres', 'Cansancio', 'Pesadez'],
                                'sintomas_aumentan' => ['Estar de pie'],
                                'sintomas_disminuyen' => ['Reposo', 'Elevación de las piernas'],
                                'enfermedades' => ['Lumbalgia'],
                                'ceap_diagnostico' => ['Primaria', 'Superficial', 'Reflujo'],
                                'tx_zonas' => ['Varicosas trunculares'],
                                'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                            ],
                        ],
                        'doppler' => [
                            'der_profundo' => $profundoNormal,
                            'der_segmentos' => $this->segmentos([
                                ['SFJ', [6.4, null, null, 5.8], 'Competente'],
                                ['GSV Muslo', [4.6, null, null, 4.2], 'Competente'],
                                ['GSV Pierna', [3.6, null, null, 3.3], 'Competente'],
                                ['SSV', [6.1, 14.0, 1.4, 5.6], 'Reflujo desde unión safenopoplítea'],
                                ['Unión safenopoplítea', [6.8, 16.0, 1.6, 6.2], 'Incompetente'],
                            ]),
                            'der_perforantes' => 'Sin perforantes incompetentes',
                            'der_trombosis' => 'Sin signos de trombosis',
                            'izq_profundo' => $profundoNormal,
                            'izq_segmentos' => $this->segmentos([
                                ['SFJ', [6.0, null, null, 5.5], 'Competente'],
                                ['GSV Muslo', [4.3, null, null, 4.0], 'Competente'],
                                ['GSV Pierna', [3.2, null, null, 3.0], 'Competente'],
                                ['SSV', [3.0, null, null, 2.7], 'Competente'],
                                [null, [null, null, null, null], null],
                            ]),
                            'izq_perforantes' => 'Sin perforantes incompetentes',
                            'izq_trombosis' => 'Sin signos de trombosis',
                            'conclusion' => 'Insuficiencia de vena safena menor derecha con unión safenopoplítea incompetente. Miembro izquierdo sin reflujo. Sistema profundo competente bilateral.',
                        ],
                        'cobro' => ['servicios' => ['consulta', 'doppler'], 'pago' => 'Tarjeta', 'nit' => '2763918'],
                    ],
                    [
                        'inicio' => '2026-11-05 10:30:00',
                        'motivo' => 'Control y plan de tratamiento',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'presion_arterial' => '124/80', 'frecuencia_cardiaca' => 74, 'temperatura' => 36.4, 'peso' => 85.6,
                            'perimetro_tobillo' => 24.5, 'perimetro_pantorrilla' => 39.0,
                            'ubicacion_patologia' => 'MID',
                            'ceap_c' => 'C2',
                            'evolucion' => 'Mejoría',
                            'estado_general' => 'Requiere nuevas sesiones',
                            'notas' => 'Refiere menos calambres con venotónico y medias. Se explica escleroterapia ecoguiada de safena menor; paciente acepta, programar en las próximas semanas.',
                            'selecciones' => [
                                'sintomas' => ['Cansancio'],
                                'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['control'], 'pago' => 'Tarjeta', 'nit' => '2763918'],
                    ],
                ],
            ],
            [
                'nombre' => 'Gabriela Sofía Ramírez Ortiz',
                'edad' => 29,
                'telefono' => '48125590',
                'lugar_residencia' => 'Chiché, Quiché',
                'estado_civil' => 'Soltero/a',
                'religion' => 'Católica',
                'estado' => 'Seguimiento',
                'citas' => [
                    [
                        'inicio' => '2026-10-27 08:30:00',
                        'motivo' => 'Primera consulta — venitas visibles en piernas',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Estética',
                            'familiar_varices' => true,
                            'alergias' => 'Ninguna conocida',
                            'gestas' => 0, 'partos' => 0, 'cesareas' => 0, 'abortos' => 0,
                            'ultima_menstruacion' => '2026-10-12',
                            'presion_arterial' => '108/68', 'frecuencia_cardiaca' => 66, 'frecuencia_respiratoria' => 14,
                            'temperatura' => 36.3, 'peso' => 55.4,
                            'ubicacion_patologia' => 'BILATERAL',
                            'ceap_c' => 'C1',
                            'notas' => 'Telangiectasias en hueco poplíteo y cara lateral de pantorrillas. Se explica escleroterapia y cuidados posteriores.',
                            'selecciones' => [
                                'zonas_pierna' => ['Pantorrilla'],
                                'sintomas' => ['Cansancio'],
                                'sintomas_aumentan' => ['Menstruación', 'Calor'],
                                'ceap_diagnostico' => ['Primaria', 'Superficial'],
                                'tx_zonas' => ['Telangiectasias'],
                                'indicaciones' => ['No se prescribe tratamiento adicional'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['consulta']],
                    ],
                    [
                        'inicio' => '2026-10-29 16:00:00',
                        'minutos' => 45,
                        'motivo' => 'Sesión de escleroterapia',
                        'estado' => 'Cancelada',
                        'motivo_cancelacion' => 'La paciente no consiguió permiso en el trabajo; pide pasarla a la siguiente semana.',
                    ],
                    [
                        'inicio' => '2026-11-06 11:00:00',
                        'minutos' => 45,
                        'motivo' => 'Sesión de escleroterapia',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Estética',
                            'presion_arterial' => '110/70', 'frecuencia_cardiaca' => 68, 'temperatura' => 36.4, 'peso' => 55.4,
                            'ubicacion_patologia' => 'BILATERAL',
                            'ceap_c' => 'C1',
                            'esclero_concentracion' => 0.50, 'esclero_forma' => 'Líquida', 'esclero_volumen' => 5.00,
                            'indicaciones_detalle' => ['Medias Compresivas' => 'Uso diurno por 2 semanas'],
                            'evolucion' => 'Igual',
                            'estado_general' => 'Requiere nuevas sesiones',
                            'notas' => 'Primera sesión. Polidocanol al 0.5 % en telangiectasias de ambas pantorrillas, sin incidentes.',
                            'selecciones' => [
                                'tx_zonas' => ['Telangiectasias'],
                                'indicaciones' => ['Medias Compresivas'],
                                'observaciones' => ['Sin complicaciones'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['esclero'], 'pago' => 'Tarjeta'],
                    ],
                ],
            ],
            [
                'nombre' => 'Lucía Beatriz Monzón Archila',
                'edad' => 47,
                'telefono' => '59063482',
                'lugar_residencia' => 'Santa Cruz del Quiché, Quiché',
                'estado_civil' => 'Divorciado/a',
                'religion' => 'Católica',
                'estado' => 'Activo',
                'citas' => [
                    [
                        'inicio' => '2026-10-26 15:00:00',
                        'minutos' => 45,
                        'motivo' => 'Dolor y várices en pierna izquierda',
                        'estado' => 'Reagendada',
                        'notas' => 'Llamó para moverla al miércoles 28 por la mañana.',
                    ],
                    [
                        'inicio' => '2026-10-28 10:00:00',
                        'minutos' => 45,
                        'motivo' => 'Dolor y várices en pierna izquierda',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'familiar_varices' => true,
                            'alergias' => 'Sulfas',
                            'gestas' => 2, 'partos' => 2, 'cesareas' => 0, 'abortos' => 0, 'hijos_vivos' => 2, 'hijos_muertos' => 0,
                            'ultima_menstruacion' => '2026-10-10',
                            'presion_arterial' => '122/78', 'frecuencia_cardiaca' => 72, 'frecuencia_respiratoria' => 16,
                            'temperatura' => 36.6, 'peso' => 70.2, 'perimetro_tobillo' => 24.0, 'perimetro_pantorrilla' => 37.0,
                            'ubicacion_patologia' => 'MII',
                            'ceap_c' => 'C3',
                            'indicaciones_detalle' => ['Venotónico' => 'Diosmina/Hesperidina 1000 mg al día', 'AINEs' => 'Ibuprofeno 400 mg cada 8 horas por 5 días si hay dolor', 'Medias Compresivas' => 'Hasta la rodilla 20-30 mmHg'],
                            'notas' => 'Paquete varicoso en cara interna de pierna izquierda, doloroso a la palpación, sin signos de flebitis. Edema vespertino.',
                            'selecciones' => [
                                'zonas_pierna' => ['Pantorrilla', 'Tobillo'],
                                'sintomas' => ['Pesadez', 'Hinchazón', 'Adormecimiento'],
                                'sintomas_aumentan' => ['Estar de pie', 'Menstruación'],
                                'sintomas_disminuyen' => ['Elevación de las piernas', 'Medicamentos'],
                                'ceap_diagnostico' => ['Primaria', 'Superficial', 'Reflujo'],
                                'tx_zonas' => ['Varicosas trunculares', 'Reticulares'],
                                'indicaciones' => ['Venotónico', 'AINEs', 'Medias Compresivas'],
                            ],
                        ],
                        'doppler' => [
                            'der_profundo' => $profundoNormal,
                            'der_segmentos' => $this->segmentos([
                                ['SFJ', [6.2, null, null, 5.7], 'Competente'],
                                ['GSV Muslo', [4.4, null, null, 4.1], 'Competente'],
                                ['GSV Pierna', [3.3, null, null, 3.0], 'Competente'],
                                ['SSV', [2.9, null, null, 2.6], 'Competente'],
                                [null, [null, null, null, null], null],
                            ]),
                            'der_perforantes' => 'Sin perforantes incompetentes',
                            'der_trombosis' => 'Sin signos de trombosis',
                            'izq_profundo' => $profundoNormal,
                            'izq_segmentos' => $this->segmentos([
                                ['SFJ', [7.0, null, null, 6.3], 'Competente'],
                                ['GSV Muslo', [5.4, 11.0, 1.2, 4.9], 'Reflujo desde perforante de Hunter'],
                                ['GSV Pierna', [5.1, 10.0, 1.3, 4.7], 'Reflujo en todo el segmento'],
                                ['SSV', [3.1, null, null, 2.8], 'Competente'],
                                ['Perforante de Hunter', [4.0, null, 0.9, 3.7], 'Incompetente'],
                            ]),
                            'izq_perforantes' => 'Perforante de Hunter incompetente, 4.0 mm',
                            'izq_trombosis' => 'Sin signos de trombosis',
                            'conclusion' => 'Insuficiencia segmentaria de vena safena mayor izquierda alimentada por perforante de Hunter incompetente; unión safenofemoral competente. MID normal. Sin trombosis.',
                        ],
                        'cobro' => ['servicios' => ['consulta', 'doppler'], 'pago' => 'Efectivo', 'nit' => '6618402'],
                    ],
                    [
                        'inicio' => '2026-11-06 14:30:00',
                        'minutos' => 45,
                        'motivo' => 'Escleroterapia con espuma — MII',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'presion_arterial' => '120/76', 'frecuencia_cardiaca' => 70, 'temperatura' => 36.5, 'peso' => 70.0,
                            'perimetro_tobillo' => 23.5, 'perimetro_pantorrilla' => 36.5,
                            'ubicacion_patologia' => 'MII',
                            'ceap_c' => 'C3',
                            'esclero_concentracion' => 2.00, 'esclero_forma' => 'Espuma', 'esclero_volumen' => 3.00,
                            'indicaciones_detalle' => ['Medias Compresivas' => 'Uso continuo 72 horas', 'AINEs' => 'Ibuprofeno 400 mg si hay dolor'],
                            'evolucion' => 'Mejoría',
                            'estado_general' => 'Respuesta satisfactoria',
                            'notas' => 'Espuma de polidocanol al 2 % ecoguiada sobre safena mayor de pierna. Cierre del segmento tratado en control ecográfico inmediato.',
                            'selecciones' => [
                                'tx_zonas' => ['Varicosas trunculares', 'Perforantes'],
                                'indicaciones' => ['Medias Compresivas', 'AINEs'],
                                'observaciones' => ['Buena respuesta', 'Dolor'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['esclero'], 'pago' => 'Efectivo', 'nit' => '6618402'],
                    ],
                ],
            ],
            [
                'nombre' => 'Jorge Luis Castillo Barrios',
                'edad' => 63,
                'telefono' => '54790138',
                'lugar_residencia' => 'Sacapulas, Quiché',
                'estado_civil' => 'Viudo/a',
                'religion' => 'Evangélica',
                'estado' => 'Seguimiento',
                'citas' => [
                    [
                        'inicio' => '2026-10-22 10:30:00',
                        'minutos' => 45,
                        'motivo' => 'Hinchazón de ambos tobillos',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'familiar_varices' => false,
                            'alergias' => 'Ninguna conocida',
                            'cirugias' => 'Herniorrafia inguinal derecha (2010)',
                            'presion_arterial' => '142/88', 'frecuencia_cardiaca' => 82, 'frecuencia_respiratoria' => 18,
                            'temperatura' => 36.8, 'peso' => 90.4, 'perimetro_tobillo' => 28.0, 'perimetro_pantorrilla' => 41.5,
                            'ubicacion_patologia' => 'BILATERAL',
                            'ceap_c' => 'C3',
                            'indicaciones_detalle' => ['Venotónico' => 'Diosmina/Hesperidina 1000 mg al día', 'Medias Compresivas' => 'Hasta la rodilla 20-30 mmHg'],
                            'notas' => 'Edema bilateral con fóvea leve. Hipertenso y diabético tipo 2 en control con médico internista. Se descarta TVP con Doppler.',
                            'selecciones' => [
                                'zonas_pierna' => ['Tobillo', 'Pies'],
                                'sintomas' => ['Hinchazón', 'Pesadez', 'Adormecimiento'],
                                'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                                'sintomas_disminuyen' => ['Elevación de las piernas'],
                                'enfermedades' => ['Diabetes', 'Alta o baja presión'],
                                'ceap_diagnostico' => ['Primaria', 'Superficial', 'Reflujo'],
                                'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                            ],
                        ],
                        'doppler' => [
                            'der_profundo' => $profundoNormal,
                            'der_segmentos' => $this->segmentos([
                                ['SFJ', [7.4, 15.0, 1.1, 6.6], 'Reflujo leve'],
                                ['GSV Muslo', [5.2, 10.0, 0.9, 4.8], 'Reflujo leve'],
                                ['GSV Pierna', [3.8, null, null, 3.4], 'Competente'],
                                ['SSV', [3.3, null, null, 3.0], 'Competente'],
                                [null, [null, null, null, null], null],
                            ]),
                            'der_perforantes' => 'Sin perforantes incompetentes',
                            'der_trombosis' => 'Sin signos de trombosis',
                            'izq_profundo' => $profundoNormal,
                            'izq_segmentos' => $this->segmentos([
                                ['SFJ', [7.9, 17.0, 1.3, 7.0], 'Reflujo moderado'],
                                ['GSV Muslo', [5.6, 12.0, 1.1, 5.1], 'Reflujo'],
                                ['GSV Pierna', [4.0, 8.0, 0.7, 3.6], 'Reflujo leve'],
                                ['SSV', [3.4, null, null, 3.1], 'Competente'],
                                [null, [null, null, null, null], null],
                            ]),
                            'izq_perforantes' => 'Sin perforantes incompetentes',
                            'izq_trombosis' => 'Sin signos de trombosis',
                            'conclusion' => 'Insuficiencia leve a moderada de safena mayor bilateral. Sistema venoso profundo sin trombosis. El edema se atribuye a componente venoso y sedentarismo.',
                        ],
                        'cobro' => ['servicios' => ['consulta', 'doppler']],
                    ],
                    [
                        'inicio' => '2026-10-27 15:00:00',
                        'motivo' => 'Control de edema',
                        'estado' => 'No Asistió',
                        'notas' => 'No contestó las llamadas de confirmación.',
                    ],
                    [
                        'inicio' => '2026-11-09 08:30:00',
                        'motivo' => 'Control de edema (reprogramada)',
                        'estado' => 'Confirmada',
                    ],
                ],
            ],
            [
                'nombre' => 'Marta Julia Sandoval Reyes',
                'edad' => 67,
                'telefono' => '41639027',
                'lugar_residencia' => 'Chinique, Quiché',
                'estado_civil' => 'Casado/a',
                'religion' => 'Católica',
                'estado' => 'Alta',
                'citas' => [
                    [
                        'inicio' => '2026-10-19 14:00:00',
                        'minutos' => 45,
                        'motivo' => 'Control anual de várices',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'familiar_varices' => true,
                            'alergias' => 'Ninguna conocida',
                            'cirugias' => 'Safenectomía izquierda (2012)',
                            'gestas' => 5, 'partos' => 5, 'cesareas' => 0, 'abortos' => 0, 'hijos_vivos' => 5, 'hijos_muertos' => 0,
                            'presion_arterial' => '130/80', 'frecuencia_cardiaca' => 76, 'frecuencia_respiratoria' => 16,
                            'temperatura' => 36.5, 'peso' => 72.8, 'perimetro_tobillo' => 24.0, 'perimetro_pantorrilla' => 35.5,
                            'ubicacion_patologia' => 'MID',
                            'ceap_c' => 'C2',
                            'indicaciones_detalle' => ['Venotónico' => 'Diosmina/Hesperidina 500 mg cada 12 horas', 'Medias Compresivas' => 'Hasta la rodilla 20-30 mmHg'],
                            'notas' => 'Antecedente de safenectomía izquierda. Várices reticulares en pierna derecha. Artritis de rodillas. Se ofrece escleroterapia de reticulares.',
                            'selecciones' => [
                                'zonas_pierna' => ['Pantorrilla'],
                                'sintomas' => ['Pesadez', 'Calambres'],
                                'sintomas_aumentan' => ['Estar de pie'],
                                'sintomas_disminuyen' => ['Medias compresivas', 'Reposo'],
                                'enfermedades' => ['Artritis'],
                                'ceap_diagnostico' => ['Primaria', 'Superficial'],
                                'tx_zonas' => ['Reticulares'],
                                'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['consulta']],
                    ],
                    [
                        'inicio' => '2026-11-03 11:30:00',
                        'minutos' => 45,
                        'motivo' => 'Escleroterapia de venas reticulares — MID',
                        'estado' => 'Completada',
                        'historia' => [
                            'consulta_por' => 'Enfermedad',
                            'presion_arterial' => '128/80', 'frecuencia_cardiaca' => 74, 'temperatura' => 36.4, 'peso' => 72.5,
                            'ubicacion_patologia' => 'MID',
                            'ceap_c' => 'C2',
                            'esclero_concentracion' => 1.00, 'esclero_forma' => 'Líquida', 'esclero_volumen' => 4.00,
                            'indicaciones_detalle' => ['Medias Compresivas' => 'Uso diurno por 3 semanas'],
                            'evolucion' => 'Mejoría',
                            'estado_general' => 'Respuesta satisfactoria',
                            'notas' => 'Polidocanol al 1 % en reticulares de cara lateral de pierna derecha. Buena tolerancia. Se da alta con control anual.',
                            'selecciones' => [
                                'tx_zonas' => ['Reticulares'],
                                'indicaciones' => ['Medias Compresivas'],
                                'observaciones' => ['Buena respuesta', 'Sin complicaciones'],
                            ],
                        ],
                        'cobro' => ['servicios' => ['esclero'], 'pago' => 'Transferencia'],
                    ],
                ],
            ],
            [
                'nombre' => 'Andrea Paola Gómez Estrada',
                'edad' => 33,
                'telefono' => '35208841',
                'lugar_residencia' => 'Joyabaj, Quiché',
                'estado_civil' => 'Unión Libre',
                'religion' => null,
                'estado' => 'Activo',
                'citas' => [
                    [
                        'inicio' => '2026-11-11 10:00:00',
                        'minutos' => 45,
                        'motivo' => 'Primera consulta — evaluación de várices tras embarazo',
                        'estado' => 'Confirmada',
                        'notas' => 'Paciente nueva, referida por Ana Lucía Herrera.',
                    ],
                ],
            ],
        ];
    }
}
