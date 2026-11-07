<?php

namespace Database\Seeders\Demo;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Auditoria\Bitacora;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Bitácora de auditoría de las semanas que cubre la demo.
 *
 * El sistema audita sesiones, cuentas, configuración y anulaciones, no cada
 * registro clínico. Lo que aquí se simula es el uso diario de la clínica: el
 * administrador solo da de alta las cuentas; la médica configura su horario y
 * los días bloqueados, entra antes de su primera cita y sale al terminar la
 * última; y recepción abre y cierra cada jornada y corrige un cobro. Los días
 * bloqueados nadie entra. Las descripciones son las mismas que escribe el
 * sistema.
 */
class AuditoriaDemo
{
    /** Hasta aquí llega la historia simulada: lo posterior es uso real de la demo. */
    public const HASTA = '2026-11-07 00:00:00';

    private const IP_RECEPCION = '192.168.1.24';

    private const IP_CONSULTORIO = '192.168.1.31';

    private const IP_ADMIN = '192.168.1.10';

    private const NAVEGADOR_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    private const NAVEGADOR_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    private const NAVEGADOR_EDGE = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0';

    public static function yaSembrada(): bool
    {
        return AuditLog::where('created_at', '<', self::HASTA)->exists();
    }

    /**
     * @return int cuántos eventos se registraron
     */
    public static function sembrar(): int
    {
        $admin = User::where('rol', 'administrador')->orderBy('id')->first();
        $medico = User::where('rol', 'medico')->orderBy('id')->first();
        $recepcion = User::where('rol', 'recepcionista')->orderBy('id')->first();

        if (! $admin || ! $medico || ! $recepcion) {
            return 0;
        }

        // Minutos «al azar» pero siempre los mismos, para que dos reseteos de
        // la demo cuenten exactamente la misma historia.
        mt_srand(20261019);
        $eventos = [];

        // ── Puesta en marcha: el administrador solo crea las cuentas ─────────
        $eventos[] = self::sesion($admin, '2026-10-12 09:12', '2026-10-12 10:03', self::IP_ADMIN, self::NAVEGADOR_EDGE);
        $eventos[] = self::evento($admin, '2026-10-12 09:20', 'usuario_creado',
            'Creó la cuenta de '.$medico->name.' ('.$medico->email.') con rol '.$medico->rol.'.', $medico,
            ['name' => $medico->name, 'email' => $medico->email, 'rol' => $medico->rol, 'activo' => true], self::IP_ADMIN, self::NAVEGADOR_EDGE);
        $eventos[] = self::evento($admin, '2026-10-12 09:24', 'usuario_creado',
            'Creó la cuenta de '.$recepcion->name.' ('.$recepcion->email.') con rol '.$recepcion->rol.'.', $recepcion,
            ['name' => $recepcion->name, 'email' => $recepcion->email, 'rol' => $recepcion->rol, 'activo' => true], self::IP_ADMIN, self::NAVEGADOR_EDGE);
        $eventos[] = self::evento($admin, '2026-10-12 09:47', 'ajustes_actualizados',
            'Modificó los datos de la clínica: clinica.nombre, clinica.especialidad, clinica.direccion, clinica.telefono.',
            null, [], self::IP_ADMIN, self::NAVEGADOR_EDGE);

        // Primer ingreso de la médica: cambia la contraseña temporal, completa
        // su perfil y los datos que firman sus informes, pone su horario y
        // bloquea el feriado del 20 de octubre.
        $eventos[] = self::sesion($medico, '2026-10-13 18:30', '2026-10-13 19:02', self::IP_CONSULTORIO, self::NAVEGADOR_MAC);
        $eventos[] = self::evento($medico, '2026-10-13 18:33', 'contrasena_cambiada', $medico->name.' cambió su contraseña.', $medico, [], self::IP_CONSULTORIO, self::NAVEGADOR_MAC);
        $eventos[] = self::evento($medico, '2026-10-13 18:40', 'perfil_actualizado', $medico->name.' modificó sus datos: telefono.', $medico,
            ['telefono' => ['antes' => null, 'despues' => $medico->telefono]], self::IP_CONSULTORIO, self::NAVEGADOR_MAC);
        $eventos[] = self::evento($medico, '2026-10-13 18:44', 'ajustes_actualizados',
            'Modificó los datos de la clínica: medico.nombre, medico.colegiado.', null, [], self::IP_CONSULTORIO, self::NAVEGADOR_MAC);
        $eventos[] = self::evento($medico, '2026-10-13 18:50', 'horario_actualizado',
            'Modificó el horario de atención o la duración de las citas.', null, [], self::IP_CONSULTORIO, self::NAVEGADOR_MAC);

        // Primer ingreso de recepción
        $eventos[] = self::sesion($recepcion, '2026-10-14 08:05', '2026-10-14 12:40', self::IP_RECEPCION, self::NAVEGADOR_WINDOWS);
        $eventos[] = self::evento($recepcion, '2026-10-14 08:07', 'contrasena_cambiada', $recepcion->name.' cambió su contraseña.', $recepcion, [], self::IP_RECEPCION, self::NAVEGADOR_WINDOWS);

        // El viernes 23, sin pacientes, la médica entra un rato a bloquear el
        // cierre de Todos los Santos
        $eventos[] = self::sesion($medico, '2026-10-23 12:11', '2026-10-23 12:26', self::IP_CONSULTORIO, self::NAVEGADOR_MAC);

        // Recepción anula un recibo emitido sin el NIT y lo vuelve a emitir
        $anulado = Invoice::where('estado', 'Anulada')->orderBy('id')->first();
        if ($anulado) {
            $eventos[] = self::evento($recepcion, $anulado->updated_at->format('Y-m-d H:i'), 'factura_anulada',
                "Anuló el documento {$anulado->correlativo} ({$anulado->nombre_receptor}). Motivo: {$anulado->motivo_anulacion}",
                $anulado, ['total' => $anulado->total, 'motivo' => $anulado->motivo_anulacion], self::IP_RECEPCION, self::NAVEGADOR_WINDOWS);
        }

        // ── Jornadas de trabajo ──────────────────────────────────────────────
        $dia = Carbon::parse('2026-10-15');
        $fin = Carbon::parse('2026-11-06');

        while ($dia->lte($fin)) {
            if (DemoSeeder::diaDeConsulta($dia)) {
                $fecha = $dia->toDateString();

                // Recepción abre la clínica y cierra al final de la tarde
                $eventos[] = self::sesion($recepcion,
                    $fecha.' 07:'.str_pad((string) mt_rand(38, 55), 2, '0', STR_PAD_LEFT),
                    $fecha.' 17:'.str_pad((string) mt_rand(5, 35), 2, '0', STR_PAD_LEFT),
                    self::IP_RECEPCION, self::NAVEGADOR_WINDOWS);

                // La médica solo entra los días que atiende
                $citas = Appointment::whereDate('fecha_hora_inicio', $fecha)
                    ->whereNotIn('estado', ['Cancelada', 'Reagendada'])
                    ->orderBy('fecha_hora_inicio')->get();

                if ($citas->isNotEmpty()) {
                    $entrada = $citas->first()->fecha_hora_inicio->copy()->subMinutes(mt_rand(12, 25));
                    $salida = $citas->last()->fecha_hora_fin->copy()->addMinutes(mt_rand(15, 40));

                    // Una mañana se equivoca de contraseña antes de entrar
                    if ($fecha === '2026-10-27') {
                        $eventos[] = self::evento($medico, $entrada->copy()->subMinute()->format('Y-m-d H:i'), 'login_fallido',
                            'Contraseña incorrecta para '.$medico->email.'.', $medico, [], self::IP_CONSULTORIO, self::NAVEGADOR_MAC, $medico->email);
                    }

                    $eventos[] = self::sesion($medico, $entrada->format('Y-m-d H:i'), $salida->format('Y-m-d H:i'), self::IP_CONSULTORIO, self::NAVEGADOR_MAC);
                }
            }
            $dia->addDay();
        }

        $filas = array_merge(...$eventos);
        usort($filas, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);

        foreach ($filas as $fila) {
            $log = new AuditLog($fila);
            $log->created_at = $fila['created_at'];
            $log->save();
        }

        return count($filas);
    }

    /**
     * Inicio y cierre de una sesión, con su duración.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function sesion(User $usuario, string $entrada, string $salida, string $ip, string $navegador): array
    {
        $segundos = (int) Carbon::parse($entrada)->diffInSeconds(Carbon::parse($salida));

        return [
            ...self::evento($usuario, $entrada, 'login', $usuario->name.' inició sesión.', $usuario, [], $ip, $navegador),
            ...self::evento($usuario, $salida, 'logout', $usuario->name.' cerró sesión tras '.Bitacora::duracion($segundos).'.',
                $usuario, ['duracion_segundos' => $segundos], $ip, $navegador),
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<int, array<string, mixed>>
     */
    private static function evento(User $autor, string $momento, string $evento, string $descripcion, ?Model $sujeto,
        array $cambios, string $ip, string $navegador, ?string $correo = null): array
    {
        return [[
            'user_id' => $autor->id,
            'usuario_nombre' => $autor->name,
            'usuario_correo' => $autor->email ?? $correo,
            'usuario_rol' => $autor->rol,
            'evento' => $evento,
            'categoria' => Bitacora::EVENTOS[$evento]['categoria'] ?? 'otros',
            'descripcion' => $descripcion,
            'sujeto_tipo' => $sujeto ? Str::snake(class_basename($sujeto)) : null,
            'sujeto_id' => $sujeto?->id,
            'cambios' => $cambios ?: null,
            'ip' => $ip,
            'user_agent' => $navegador,
            'created_at' => Carbon::parse($momento)->setSecond(mt_rand(0, 59)),
        ]];
    }
}
