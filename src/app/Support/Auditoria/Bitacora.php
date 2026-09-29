<?php

namespace App\Support\Auditoria;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * La bitácora de auditoría.
 *
 * No registra todo: registrar cada lectura llenaría la tabla de ruido y nadie
 * la leería. Registra lo que el administrador querría poder reconstruir
 * después —quién entró y cuándo, quién intentó entrar sin poder, quién cambió
 * una cuenta, quién anuló un cobro o desactivó un expediente—.
 *
 * Escribir en la bitácora nunca tumba la operación que se está auditando: si
 * la inserción falla se reporta al log de Laravel y la petición sigue. Un
 * inicio de sesión que no entra porque la auditoría falló sería peor remedio
 * que la enfermedad.
 */
class Bitacora
{
    /**
     * Los eventos que se registran, con su etiqueta y su categoría. La pantalla
     * pide este catálogo para armar los filtros, así que un evento nuevo solo
     * se declara aquí.
     *
     * @var array<string, array{etiqueta: string, categoria: string}>
     */
    public const EVENTOS = [
        'login' => ['etiqueta' => 'Inicio de sesión', 'categoria' => 'sesion'],
        'login_fallido' => ['etiqueta' => 'Inicio de sesión fallido', 'categoria' => 'sesion'],
        'logout' => ['etiqueta' => 'Cierre de sesión', 'categoria' => 'sesion'],

        'usuario_creado' => ['etiqueta' => 'Usuario creado', 'categoria' => 'usuarios'],
        'usuario_actualizado' => ['etiqueta' => 'Usuario modificado', 'categoria' => 'usuarios'],
        'usuario_desactivado' => ['etiqueta' => 'Usuario desactivado', 'categoria' => 'usuarios'],
        'usuario_eliminado' => ['etiqueta' => 'Usuario eliminado', 'categoria' => 'usuarios'],

        'perfil_actualizado' => ['etiqueta' => 'Perfil propio modificado', 'categoria' => 'cuenta'],
        'contrasena_cambiada' => ['etiqueta' => 'Contraseña cambiada', 'categoria' => 'cuenta'],
        'contrasena_restablecida' => ['etiqueta' => 'Contraseña restablecida por correo', 'categoria' => 'cuenta'],

        'factura_anulada' => ['etiqueta' => 'Documento de cobro anulado', 'categoria' => 'registros'],
        'paciente_desactivado' => ['etiqueta' => 'Paciente desactivado', 'categoria' => 'registros'],
        'paciente_activado' => ['etiqueta' => 'Paciente reactivado', 'categoria' => 'registros'],
        'historia_desactivada' => ['etiqueta' => 'Historia clínica desactivada', 'categoria' => 'registros'],
        'estudio_desactivado' => ['etiqueta' => 'Ecodöppler desactivado', 'categoria' => 'registros'],
        'servicio_eliminado' => ['etiqueta' => 'Servicio eliminado', 'categoria' => 'registros'],

        'ajustes_actualizados' => ['etiqueta' => 'Datos de la clínica modificados', 'categoria' => 'configuracion'],
        'horario_actualizado' => ['etiqueta' => 'Horario de atención modificado', 'categoria' => 'configuracion'],
    ];

    /** @var array<string, string> */
    public const CATEGORIAS = [
        'sesion' => 'Sesiones',
        'usuarios' => 'Gestión de usuarios',
        'cuenta' => 'Cuenta propia',
        'registros' => 'Registros sensibles',
        'configuracion' => 'Configuración',
    ];

    /**
     * Anotar un evento.
     *
     * @param  User|null  $autor  Quién lo hizo. Por omisión, el usuario
     *                            autenticado; en el inicio de sesión todavía no
     *                            lo hay y se pasa a mano.
     * @param  string|null  $correo  Para los intentos sin cuenta: lo que se escribió.
     * @param  array<string, mixed>  $cambios
     */
    public static function registrar(
        string $evento,
        string $descripcion,
        ?Model $sujeto = null,
        array $cambios = [],
        ?User $autor = null,
        ?string $correo = null,
    ): ?AuditLog {
        try {
            $request = request();
            $autor ??= $request->user();

            return AuditLog::create([
                'user_id' => $autor?->id,
                'usuario_nombre' => $autor?->name,
                'usuario_correo' => $autor?->email ?? $correo,
                'usuario_rol' => $autor?->rol,
                'evento' => $evento,
                'categoria' => self::EVENTOS[$evento]['categoria'] ?? 'otros',
                'descripcion' => Str::limit($descripcion, 497),
                'sujeto_tipo' => $sujeto ? Str::snake(class_basename($sujeto)) : null,
                'sujeto_id' => $sujeto?->getKey(),
                'cambios' => $cambios ?: null,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 252) ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Lo que acaba de cambiar en un modelo guardado, como {campo: {antes, despues}}.
     *
     * Se llama después del update(): Eloquent guarda entonces en getPrevious()
     * los valores que había. Las contraseñas no se copian —ni siquiera el
     * hash—: basta con saber que cambió.
     *
     * @param  array<int, string>  $ocultos
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    public static function diferencias(Model $modelo, array $ocultos = ['password', 'remember_token']): array
    {
        $antes = $modelo->getPrevious();
        $cambios = [];

        foreach ($modelo->getChanges() as $campo => $despues) {
            if (in_array($campo, ['updated_at', 'created_at', 'updated_by'], true)) {
                continue;
            }

            $cambios[$campo] = in_array($campo, $ocultos, true)
                ? ['antes' => '••••', 'despues' => '(cambiada)']
                : ['antes' => $antes[$campo] ?? null, 'despues' => $despues];
        }

        return $cambios;
    }

    /**
     * «2 h 15 min», «45 min», «menos de un minuto».
     */
    public static function duracion(int $segundos): string
    {
        $minutos = intdiv(max(0, $segundos), 60);

        if ($minutos < 1) {
            return 'menos de un minuto';
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        if ($horas === 0) {
            return $resto.' min';
        }

        return $resto === 0 ? $horas.' h' : $horas.' h '.$resto.' min';
    }
}
