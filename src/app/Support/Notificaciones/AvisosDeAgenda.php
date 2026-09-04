<?php

namespace App\Support\Notificaciones;

use App\Models\Appointment;
use App\Models\NotificationDismissal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Los avisos del campanario salen de la agenda: no hay una tabla de
 * notificaciones ni una tarea que las cree de madrugada.
 *
 * La razón es de mantenimiento. Una fila creada anoche pasa a mentir en cuanto
 * alguien reagenda o cancela la cita, y habría que salir a corregirla desde
 * cada uno de esos caminos. Calculándolas, la respuesta siguiente ya sale bien
 * sola. Lo único que sí hay que recordar —qué descartó cada quien a mano— vive
 * en notification_dismissals.
 */
class AvisosDeAgenda
{
    /**
     * Citas que ya no esperan a nadie: canceladas o con el paso resuelto.
     */
    private const ESTADOS_RESUELTOS = ['Cancelada', 'Completada', 'No Asistió'];

    public function __construct(private readonly User $usuario)
    {
    }

    /**
     * Desde ahora mismo hasta el final del día de mañana.
     *
     * El extremo izquierdo es «ahora» y no «hoy a las cero horas»: de ahí sale
     * el borrado automático que se pidió, porque una cita deja de aparecer en
     * cuanto pasa su hora sin que nadie tenga que borrar nada.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function ventana(): array
    {
        return [now(), now()->addDay()->endOfDay()];
    }

    /**
     * Avisos visibles para el usuario, en orden de agenda.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function avisos(): Collection
    {
        [$desde, $hasta] = $this->ventana();

        $consulta = Appointment::query()
            ->with(['patient:id,nombre,telefono', 'medico:id,name'])
            ->whereBetween('fecha_hora_inicio', [$desde, $hasta])
            ->whereNotIn('estado', self::ESTADOS_RESUELTOS)
            ->orderBy('fecha_hora_inicio');

        // El médico recibe avisos de su propia agenda; recepción y
        // administración, los de la clínica entera.
        if ($this->usuario->rol === 'medico') {
            $consulta->where('medico_id', $this->usuario->id);
        }

        $descartadas = $this->clavesDescartadas();

        return $consulta->get()
            ->map(fn (Appointment $cita): array => $this->aviso($cita))
            ->reject(fn (array $aviso): bool => in_array($aviso['clave'], $descartadas, true))
            ->values();
    }

    /**
     * Claves que este usuario ya descartó a mano.
     *
     * @return array<int, string>
     */
    public function clavesDescartadas(): array
    {
        return NotificationDismissal::query()
            ->where('user_id', $this->usuario->id)
            ->pluck('clave')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function aviso(Appointment $cita): array
    {
        $inicio = $cita->fecha_hora_inicio;

        return [
            'clave' => "cita:{$cita->id}",
            'tipo' => 'cita',
            'dia' => $inicio->isToday() ? 'hoy' : 'manana',
            'cita_id' => $cita->id,
            'fecha_hora_inicio' => $inicio->toIso8601String(),
            'hora' => $inicio->format('H:i'),
            'estado' => $cita->estado,
            'motivo' => $cita->motivo,
            'paciente' => $cita->patient ? [
                'id' => $cita->patient->id,
                'nombre' => $cita->patient->nombre,
                'telefono' => $cita->patient->telefono,
            ] : null,
            'medico' => $cita->medico ? [
                'id' => $cita->medico->id,
                'name' => $cita->medico->name,
            ] : null,
        ];
    }
}
