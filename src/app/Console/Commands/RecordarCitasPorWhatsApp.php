<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Support\Ajustes\Ajustes;
use App\Support\WhatsApp\Resultado;
use App\Support\WhatsApp\WhatsApp;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * El recordatorio de WhatsApp, 24 horas antes de la cita.
 *
 * Corre cada minuto (routes/console.php) y manda el aviso a las citas que
 * empiezan dentro de 24 horas: la de las cinco de la tarde del jueves recibe
 * su mensaje el miércoles a las cinco.
 *
 * No busca solo las que caen en el minuto exacto, sino las de la última hora
 * que aún no tienen recordatorio. Así, si el servicio de WhatsApp estuvo caído
 * un rato o el contenedor se reinició, el aviso sale en cuanto vuelve en vez
 * de perderse. Una hora es el límite: pasado eso ya no es «un día antes».
 */
class RecordarCitasPorWhatsApp extends Command
{
    protected $signature = 'citas:recordar-whatsapp';

    protected $description = 'Manda por WhatsApp el recordatorio de las citas que empiezan dentro de 24 horas';

    /** Cuánto tarde puede salir un recordatorio y seguir teniendo sentido. */
    private const MARGEN_MINUTOS = 60;

    /** Las citas que todavía van a ocurrir. */
    private const ESTADOS = ['Programada', 'Confirmada', 'Reagendada'];

    private const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    private const MESES = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    public function handle(): int
    {
        if (! Ajustes::obtener('whatsapp.recordatorios')) {
            return self::SUCCESS;
        }

        // Las horas de las citas son hora de pared de la clínica, igual que
        // now() con APP_TIMEZONE=America/Guatemala: se comparan tal cual.
        $limite = now()->addDay();

        $citas = Appointment::with('patient')
            ->whereIn('estado', self::ESTADOS)
            ->whereNull('recordatorio_at')
            ->where('fecha_hora_inicio', '<=', $limite)
            ->where('fecha_hora_inicio', '>', $limite->copy()->subMinutes(self::MARGEN_MINUTOS))
            ->orderBy('fecha_hora_inicio')
            ->get();

        foreach ($citas as $cita) {
            $resultado = $this->recordar($cita);

            // Si el servicio no está, tampoco lo estará para la siguiente
            // cita de esta vuelta: se deja para el próximo minuto.
            if ($resultado === Resultado::SinServicio) {
                $this->warn('WhatsApp no está disponible; se reintentará en el próximo minuto.');
                break;
            }
        }

        return self::SUCCESS;
    }

    private function recordar(Appointment $cita): Resultado
    {
        $telefono = $cita->patient?->telefono;

        $resultado = $telefono
            ? WhatsApp::enviar($telefono, $this->mensaje($cita))
            : Resultado::SinWhatsApp;

        if ($resultado->reintentable()) {
            Log::warning('No se pudo mandar el recordatorio de la cita por WhatsApp.', [
                'cita' => $cita->id,
                'resultado' => $resultado->value,
            ]);

            return $resultado;
        }

        // Enviado, o imposible de enviar: en los dos casos no se vuelve a
        // intentar. saveQuietly para no pasar por el evento que olvida el
        // recordatorio al cambiar la hora; aquí la hora no cambia.
        $cita->recordatorio_at = now();
        $cita->recordatorio_resultado = $resultado->value;
        $cita->saveQuietly();

        $this->line("Cita {$cita->id}: {$resultado->value}");

        return $resultado;
    }

    private function mensaje(Appointment $cita): string
    {
        $inicio = Carbon::parse($cita->fecha_hora_inicio);
        $clinica = config('reportes.centro.nombre') ?: config('app.name');
        $telefonoClinica = config('reportes.centro.telefono');

        $fecha = sprintf(
            '%s %d de %s',
            self::DIAS[$inicio->dayOfWeek],
            $inicio->day,
            self::MESES[$inicio->month],
        );

        $lineas = [
            "Hola, {$cita->patient->nombre}.",
            '',
            "Le recordamos su cita en *{$clinica}* mañana, {$fecha}, a las *{$inicio->format('g:i A')}*.",
            '',
            $telefonoClinica
                ? "Si no puede asistir, por favor avísenos al {$telefonoClinica}."
                : 'Si no puede asistir, por favor avísenos respondiendo a este mensaje.',
        ];

        return implode("\n", $lineas);
    }
}
