<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\ClinicalHistory;
use App\Models\ClinicalOption;
use App\Models\DopplerReport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Support\Ajustes\Ajustes;
use App\Support\Facturacion\Totales;
use App\Support\MapeoVenoso\Catalogo;
use Database\Seeders\Demostracion\Expedientes;
use Database\Seeders\Demostracion\LaminaMapeo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Datos de demostración para enseñar los informes impresos.
 *
 * No es un seeder de desarrollo ni de pruebas: lo que siembra son seis
 * expedientes clínicos completos y coherentes entre sí —historia, Ecodöppler,
 * mapeo venoso, recibos y citas— para poder emitir los PDF del sistema y
 * enseñarlos tal como se verán en uso. Los datos de `Expedientes` son
 * inventados, pero los valores son los que un flebólogo escribiría: un reflujo
 * de 2.4 segundos, una perforante de 3.9 mm, un recibo de Q750.
 *
 * Se ejecuta solo:
 *
 *     php artisan db:seed --class=DemostracionSeeder
 *
 * y no está en DatabaseSeeder a propósito: `db:seed` a secas tiene que seguir
 * dejando la base como está hoy.
 *
 * Es repetible. Antes de sembrar borra sus propios expedientes —los de los seis
 * pacientes que crea, por nombre— con todo lo que cuelga de ellos, incluidas
 * las láminas de mapeo en disco. No toca ningún otro paciente.
 */
class DemostracionSeeder extends Seeder
{
    /**
     * Serie propia para los recibos de demostración, separada de la serie 'A'
     * de la clínica: así se distinguen a simple vista de un cobro real y
     * borrarlos no abre un hueco en el correlativo bueno.
     */
    private const SERIE = 'D';

    public function run(): void
    {
        // Catálogos de los que dependen los expedientes. Los tres son
        // idempotentes, así que esto también funciona sobre una base recién
        // migrada y vacía.
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ClinicalOptionSeeder::class,
            AjusteSeeder::class,
        ]);

        $medico = User::where('email', 'medico@vens.com')->firstOrFail();
        $recepcion = User::where('email', 'recepcion@vens.com')->firstOrFail();

        $this->membrete();
        $this->tarifario();

        $hoy = Carbon::today();
        $expedientes = Expedientes::todos($hoy);

        $this->limpiar(array_column(array_column($expedientes, 'paciente'), 'nombre'));

        foreach ($expedientes as $expediente) {
            $this->sembrarExpediente($expediente, $hoy, $medico, $recepcion);
        }

        $this->command?->info('  Sembrados '.count($expedientes).' expedientes de demostración.');
    }

    /*
    |--------------------------------------------------------------------------
    | Ajustes de la clínica
    |--------------------------------------------------------------------------
    */

    /**
     * Completa lo que le falta al membrete de los recibos.
     *
     * No inventa los datos de la clínica: el nombre, la dirección, el teléfono
     * y quién firma se ajustan desde la pantalla de configuración y son los
     * únicos que deben salir impresos. Lo que sí hace falta para que un recibo
     * de muestra se lea completo es la dirección fiscal del emisor, que es la
     * misma de la clínica y que nadie vuelve a teclear al configurar.
     *
     * Si la clínica todavía no ha puesto su dirección, esto no escribe nada:
     * una dirección inventada en un documento de cobro es peor que un hueco.
     */
    private function membrete(): void
    {
        $direccion = Ajustes::obtener('clinica.direccion');

        if (filled($direccion) && blank(Ajustes::obtener('facturacion.direccion'))) {
            Ajustes::guardar(['facturacion.direccion' => $direccion]);
        }
    }

    /**
     * Tarifario con los precios que usan los recibos de los expedientes.
     *
     * El catálogo solo rellena el formulario de cobro; los recibos ya emitidos
     * guardan su propia descripción y su propio precio, así que esto no los
     * altera.
     */
    private function tarifario(): void
    {
        $servicios = [
            ['nombre' => 'Consulta de primera vez — Flebología', 'descripcion' => 'Valoración clínica inicial, exploración y plan de estudio.', 'precio' => 400.00],
            ['nombre' => 'Consulta de control', 'descripcion' => 'Seguimiento de paciente en tratamiento.', 'precio' => 275.00],
            ['nombre' => 'Consulta de control y curación de úlcera venosa', 'descripcion' => 'Control clínico con curación y recambio de vendaje.', 'precio' => 275.00],
            ['nombre' => 'Ecodöppler venoso de miembros inferiores (bilateral)', 'descripcion' => 'Estudio dúplex de ambos miembros, con informe.', 'precio' => 650.00],
            ['nombre' => 'Sesión de escleroterapia con espuma — un miembro', 'descripcion' => 'Escleroterapia ecoguiada de tronco safeno y colaterales.', 'precio' => 750.00],
            ['nombre' => 'Sesión de escleroterapia con espuma — bilateral', 'descripcion' => 'Escleroterapia ecoguiada de ambos miembros en la misma sesión.', 'precio' => 850.00],
            ['nombre' => 'Sesión de escleroterapia de telangiectasias', 'descripcion' => 'Escleroterapia de arañas vasculares y venas reticulares.', 'precio' => 650.00],
            ['nombre' => 'Sesión de escleroterapia de telangiectasias (retoque)', 'descripcion' => 'Sesión corta sobre lesiones residuales.', 'precio' => 450.00],
            ['nombre' => 'Media compresiva hasta la rodilla 20-30 mmHg', 'descripcion' => 'Media de compresión graduada, por unidad.', 'precio' => 385.00],
            ['nombre' => 'Vendaje multicapa de compresión', 'descripcion' => 'Material para una aplicación de vendaje inelástico multicapa.', 'precio' => 420.00],
        ];

        foreach ($servicios as $servicio) {
            Service::firstOrCreate(['nombre' => $servicio['nombre']], $servicio);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Limpieza
    |--------------------------------------------------------------------------
    */

    /**
     * Borra los expedientes sembrados por una ejecución anterior.
     *
     * Se borra por nombre exacto y solo lo de estos seis pacientes. Las
     * cascadas de la base se llevan citas, historias, selecciones, estudios y
     * renglones de los recibos, pero no dos cosas: las facturas, que están
     * atadas al paciente con `restrict` para que un cobro no desaparezca por
     * accidente, y las láminas de mapeo, que viven en disco.
     *
     * @param  array<int, string>  $nombres
     */
    private function limpiar(array $nombres): void
    {
        $pacientes = Patient::whereIn('nombre', $nombres)->get();

        if ($pacientes->isEmpty()) {
            return;
        }

        $ids = $pacientes->pluck('id');

        foreach (ClinicalHistory::whereIn('patient_id', $ids)->pluck('mapeo_venoso_path') as $ruta) {
            if ($ruta) {
                Storage::disk('public')->delete($ruta);
            }
        }

        DB::transaction(function () use ($ids) {
            Invoice::whereIn('patient_id', $ids)->delete();
            DopplerReport::whereIn('patient_id', $ids)->delete();
            ClinicalHistory::whereIn('patient_id', $ids)->delete();
            Appointment::whereIn('patient_id', $ids)->delete();
            Patient::whereIn('id', $ids)->delete();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Siembra de un expediente
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $expediente
     */
    private function sembrarExpediente(array $expediente, Carbon $hoy, User $medico, User $recepcion): void
    {
        $paciente = Patient::create($expediente['paciente']);

        // Las historias se crean primero porque el Ecodöppler cuelga de una de
        // ellas y el recibo, cuando lo hay, se ata a la consulta que cobra.
        $historias = [];

        foreach ($expediente['consultas'] as $consulta) {
            $historias[] = $this->consulta($consulta, $paciente, $medico, $recepcion);
        }

        foreach ($expediente['doppler'] ?? [] as $estudio) {
            $this->estudio($estudio, $paciente, $historias, $medico, $recepcion);
        }

        foreach ($expediente['citas'] ?? [] as $cita) {
            $this->cita($cita, $paciente, $hoy, $medico, $recepcion);
        }
    }

    /**
     * Una consulta: su cita ya atendida, la historia clínica, las selecciones
     * del catálogo, el mapeo venoso y el recibo.
     *
     * @param  array<string, mixed>  $consulta
     */
    private function consulta(array $consulta, Patient $paciente, User $medico, User $recepcion): ClinicalHistory
    {
        $fecha = Carbon::parse($consulta['fecha']);

        $cita = $this->cita(
            array_merge($consulta['cita'], ['estado' => 'Completada', 'fecha' => $consulta['fecha']]),
            $paciente,
            $fecha,
            $medico,
            $recepcion
        );

        $historia = ClinicalHistory::create(array_merge($consulta['campos'], [
            'patient_id' => $paciente->id,
            'appointment_id' => $cita->id,
            'fecha_consulta' => $consulta['fecha'],
            'estado_registro' => $consulta['estado_registro'] ?? 'Finalizada',
            'activo' => true,
            'created_by' => $medico->id,
            'updated_by' => $medico->id,
        ]));

        $this->selecciones($historia, $consulta['selecciones'] ?? []);

        if (isset($consulta['mapeo'])) {
            $this->mapeo($historia, $consulta['mapeo'], $fecha);
        }

        if (isset($consulta['cobro'])) {
            $this->recibo($consulta['cobro'], $paciente, $historia, $consulta['fecha'], $recepcion);
        }

        return $historia;
    }

    /**
     * Marca las opciones del catálogo que el formulario habría marcado.
     *
     * @param  array<string, array<int, string>>  $selecciones
     */
    private function selecciones(ClinicalHistory $historia, array $selecciones): void
    {
        $ids = [];

        foreach ($selecciones as $categoria => $valores) {
            foreach ($valores as $valor) {
                $opcion = ClinicalOption::where('categoria', $categoria)->where('valor', $valor)->first();

                if ($opcion === null) {
                    $this->command?->warn("  Opción desconocida: {$categoria} / {$valor}");

                    continue;
                }

                $ids[] = $opcion->id;
            }
        }

        $historia->options()->sync($ids);
    }

    /**
     * Documento vectorial del mapeo y su lámina en PNG.
     *
     * En uso real el PNG lo exporta el editor en el navegador; aquí lo compone
     * LaminaMapeo sobre la misma plantilla, para que el expediente sembrado
     * tenga la imagen y las tablas que el reporte construye a partir de los
     * objetos.
     *
     * @param  array<int, array<string, mixed>>  $objetos
     */
    private function mapeo(ClinicalHistory $historia, array $objetos, Carbon $fecha): void
    {
        $documento = [
            'version' => 1,
            'plantilla' => Catalogo::plantillaId(),
            'objetos' => $objetos,
        ];

        $ruta = "mapeos-venosos/{$historia->id}/".Str::uuid()->toString().'.png';

        Storage::disk('public')->put($ruta, LaminaMapeo::png($documento));

        $historia->update([
            'mapeo_venoso_path' => $ruta,
            'mapeo_venoso_datos' => $documento,
            // Se dibuja en la consulta, no hoy: la fecha sale impresa en el pie
            // de la lámina y ponerla de hoy haría que un expediente de hace
            // cuatro meses dijera que su mapeo se actualizó esta mañana.
            'mapeo_venoso_updated_at' => $fecha->copy()->setTime(11, 20),
        ]);
    }

    /**
     * Reporte de Ecodöppler, con su cita y su recibo.
     *
     * @param  array<string, mixed>  $estudio
     * @param  array<int, ClinicalHistory>  $historias
     */
    private function estudio(array $estudio, Patient $paciente, array $historias, User $medico, User $recepcion): void
    {
        $fecha = Carbon::parse($estudio['fecha']);

        $this->cita(
            array_merge($estudio['cita'], ['estado' => 'Completada', 'fecha' => $estudio['fecha']]),
            $paciente,
            $fecha,
            $medico,
            $recepcion
        );

        $historia = $historias[$estudio['consulta'] ?? 0] ?? null;

        DopplerReport::create(array_merge($estudio['campos'], [
            'patient_id' => $paciente->id,
            'clinical_history_id' => $historia?->id,
            'fecha_estudio' => $estudio['fecha'],
            'estado_registro' => 'Finalizada',
            'activo' => true,
            'created_by' => $medico->id,
            'updated_by' => $medico->id,
        ]));

        if (isset($estudio['cobro'])) {
            $this->recibo($estudio['cobro'], $paciente, $historia, $estudio['fecha'], $recepcion);
        }
    }

    /**
     * Una cita de la agenda.
     *
     * `dias` cuenta hacia atrás desde hoy, como el resto de los datos de
     * muestra, así que un número negativo es una cita futura: es lo que hace
     * que la agenda tenga algo pendiente el día de la demostración.
     *
     * @param  array<string, mixed>  $cita
     */
    private function cita(array $cita, Patient $paciente, Carbon $referencia, User $medico, User $recepcion): Appointment
    {
        $fecha = isset($cita['fecha'])
            ? Carbon::parse($cita['fecha'])
            : $referencia->copy()->subDays((int) $cita['dias']);

        [$hora, $minuto] = array_map('intval', explode(':', $cita['hora']));

        $inicio = $fecha->copy()->setTime($hora, $minuto);
        $duracion = (int) ($cita['duracion'] ?? 30);

        return Appointment::create([
            'patient_id' => $paciente->id,
            'medico_id' => $medico->id,
            'created_by' => $recepcion->id,
            'fecha_hora_inicio' => $inicio,
            'fecha_hora_fin' => $inicio->copy()->addMinutes($duracion),
            'motivo' => $cita['motivo'],
            'estado' => $cita['estado'] ?? 'Programada',
            'motivo_cancelacion' => $cita['cancelacion'] ?? null,
            'notas' => $cita['notas'] ?? null,
        ]);
    }

    /**
     * Recibo de la consulta, con sus renglones y sus cuentas.
     *
     * Los totales se calculan con la misma clase que usa el módulo de cobros,
     * y no a mano: un recibo de muestra con el IVA mal desglosado es lo primero
     * que alguien va a comprobar con una calculadora.
     *
     * @param  array<string, mixed>  $cobro
     */
    private function recibo(array $cobro, Patient $paciente, ?ClinicalHistory $historia, string $fecha, User $recepcion): void
    {
        $iva = (float) config('facturacion.iva_porcentaje', 12);

        $renglones = array_map(
            fn (array $renglon) => $renglon + ['tipo' => 'S', 'descuento' => 0],
            $cobro['renglones']
        );

        $cuentas = Totales::calcular($renglones, $iva);

        $factura = Invoice::create([
            'patient_id' => $paciente->id,
            'clinical_history_id' => $historia?->id,
            'created_by' => $recepcion->id,
            'tipo' => Invoice::TIPO_RECIBO,
            'serie' => self::SERIE,
            'numero' => Invoice::siguienteNumero(self::SERIE),
            'fecha_emision' => $fecha,
            'nit_receptor' => $cobro['nit'] ?? Invoice::NIT_CONSUMIDOR_FINAL,
            'nombre_receptor' => $paciente->nombre,
            'direccion_receptor' => $paciente->lugar_residencia,
            'moneda' => config('facturacion.moneda', 'GTQ'),
            'subtotal' => $cuentas['subtotal'],
            'descuento' => $cuentas['descuento'],
            'total' => $cuentas['total'],
            'iva_porcentaje' => $iva,
            'iva_monto' => $cuentas['iva_monto'],
            'metodo_pago' => $cobro['metodo_pago'] ?? 'Efectivo',
            'estado' => 'Emitida',
            'observaciones' => $cobro['observaciones'] ?? null,
        ]);

        foreach ($cuentas['renglones'] as $renglon) {
            InvoiceItem::create(['invoice_id' => $factura->id] + $renglon);
        }
    }
}
