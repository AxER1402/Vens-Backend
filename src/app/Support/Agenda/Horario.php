<?php

namespace App\Support\Agenda;

use App\Support\Ajustes\Ajustes;
use Carbon\CarbonInterface;

/**
 * El horario de atención de la clínica.
 *
 * Hasta ahora la agenda solo sabía de bloqueos puntuales —feriados,
 * vacaciones, cierres—, así que aceptaba una cita a las tres de la madrugada
 * de un domingo sin decir nada.
 *
 * Nace permisivo a propósito: mientras no se configure un horario no se
 * valida nada. Una clínica que ya tiene la agenda llena no debería
 * encontrarse, por haber actualizado el sistema, con que no puede tocar las
 * citas que ya tenía porque caen fuera de un horario que nadie eligió.
 */
class Horario
{
    /**
     * Los días, en el orden en que se leen y con el número que les da Carbon
     * (0 es domingo).
     *
     * @var array<int, string>
     */
    public const DIAS = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        0 => 'domingo',
    ];

    /** Cómo se nombra cada día en un mensaje. */
    public const ETIQUETAS = [
        'lunes' => 'los lunes',
        'martes' => 'los martes',
        'miercoles' => 'los miércoles',
        'jueves' => 'los jueves',
        'viernes' => 'los viernes',
        'sabado' => 'los sábados',
        'domingo' => 'los domingos',
    ];

    /** Duración por defecto de una cita, en minutos. */
    public const DURACION_POR_DEFECTO = 30;

    /**
     * El horario configurado, o un arreglo vacío si no se ha configurado uno.
     *
     * @return array<string, array{activo: bool, abre: ?string, cierra: ?string}>
     */
    public static function configurado(): array
    {
        $horario = Ajustes::obtener('agenda.horario');

        return is_array($horario) ? $horario : [];
    }

    public static function duracionCita(): int
    {
        $minutos = (int) Ajustes::obtener('agenda.duracion_cita');

        return $minutos > 0 ? $minutos : self::DURACION_POR_DEFECTO;
    }

    /**
     * Qué impide agendar en este rango, o null si nada lo impide.
     *
     * Devuelve el motivo ya redactado porque quien pregunta es un controlador
     * que solo lo va a poner en un 422: repartir la frase entre los dos haría
     * que el mismo rechazo se explicara distinto en cada sitio.
     */
    public static function motivoFuera(CarbonInterface $inicio, CarbonInterface $fin): ?string
    {
        $horario = self::configurado();

        if ($horario === []) {
            return null;
        }

        $dia = self::DIAS[$inicio->dayOfWeek] ?? null;
        $tramo = $dia !== null ? ($horario[$dia] ?? null) : null;
        $cuando = self::ETIQUETAS[$dia] ?? 'ese día';

        if (! $tramo || ! ($tramo['activo'] ?? false)) {
            return "La clínica no atiende {$cuando}.";
        }

        $abre = $tramo['abre'] ?? null;
        $cierra = $tramo['cierra'] ?? null;

        // Un día activo pero sin horas es un día abierto de par en par, no un
        // día cerrado: quien marcó la casilla dijo que sí atiende.
        if (! $abre || ! $cierra) {
            return null;
        }

        // Una cita que termina al día siguiente se sale del horario por
        // definición, y comparando solo las horas parecería que cabe.
        if (! $fin->isSameDay($inicio)) {
            return "La cita debe terminar el mismo día. {$cuando} se atiende de {$abre} a {$cierra}.";
        }

        $desde = $inicio->format('H:i');
        $hasta = $fin->format('H:i');

        if ($desde < $abre || $hasta > $cierra) {
            $frase = ucfirst($cuando);

            return "{$frase} se atiende de {$abre} a {$cierra}.";
        }

        return null;
    }
}
