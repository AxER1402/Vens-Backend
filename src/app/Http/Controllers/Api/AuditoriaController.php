<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Auditoria\Bitacora;
use App\Support\Listados\Pagina;
use App\Support\Sesion\VencimientoDeSesion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * La bitácora de auditoría, para el administrador.
 *
 * Solo lectura: las filas las escriben los controladores que hacen las cosas
 * (ver Bitacora::registrar). Desde aquí no se puede borrar ni corregir nada.
 */
class AuditoriaController extends Controller
{
    /**
     * Listar la bitácora, de lo más reciente a lo más antiguo.
     *
     * Filtros: categoria, evento, user_id, desde, hasta (Y-m-d, ambos
     * inclusive) y search (en la descripción, el nombre, el correo o la IP).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $query = AuditLog::query();

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        if ($request->filled('evento')) {
            $query->where('evento', $request->input('evento'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('desde')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('desde'))->startOfDay());
        }

        if ($request->filled('hasta')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('hasta'))->endOfDay());
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'like', "%{$search}%")
                    ->orWhere('usuario_nombre', 'like', "%{$search}%")
                    ->orWhere('usuario_correo', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        // El id desempata las filas del mismo segundo, que en un login seguido
        // de una modificación son habituales.
        $query->orderByDesc('created_at')->orderByDesc('id');

        return response()->json(Pagina::respuesta($request, $query), 200);
    }

    /**
     * El catálogo de eventos y categorías, para los filtros de la pantalla.
     */
    public function catalogo(): JsonResponse
    {
        $eventos = [];

        foreach (Bitacora::EVENTOS as $clave => $evento) {
            $eventos[] = ['clave' => $clave] + $evento;
        }

        $categorias = [];

        foreach (Bitacora::CATEGORIAS as $clave => $etiqueta) {
            $categorias[] = ['clave' => $clave, 'etiqueta' => $etiqueta];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'eventos' => $eventos,
                'categorias' => $categorias,
                'usuarios' => User::select('id', 'name', 'rol')->orderBy('name')->get(),
            ],
        ], 200);
    }

    /**
     * El resumen de la cabecera y los accesos de cada usuario.
     *
     * Los accesos son lo que el administrador suele venir a mirar: cuándo
     * entró cada quien por última vez, cuántas veces esta semana, si alguien
     * está fallando la contraseña y quién tiene ahora mismo la sesión abierta.
     */
    public function resumen(): JsonResponse
    {
        $hoy = now()->startOfDay();
        $semana = now()->subDays(7);
        $ultimas24 = now()->subDay();

        $sesionesVivas = $this->sesionesVivas();

        $ingresos = AuditLog::query()
            ->whereNotNull('user_id')
            ->where('evento', 'login')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('MAX(created_at) as ultimo'))
            ->pluck('ultimo', 'user_id');

        $ingresosSemana = $this->contarPorUsuario('login', $semana);
        $fallidosSemana = $this->contarPorUsuario('login_fallido', $semana);

        $usuarios = User::select('id', 'name', 'email', 'rol', 'activo')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($ingresos, $ingresosSemana, $fallidosSemana, $sesionesVivas) {
                $sesion = $sesionesVivas->get($user->id);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'rol' => $user->rol,
                    'activo' => $user->activo,
                    'ultimo_ingreso' => isset($ingresos[$user->id])
                        ? Carbon::parse($ingresos[$user->id])->toIso8601String()
                        : null,
                    'ingresos_semana' => (int) ($ingresosSemana[$user->id] ?? 0),
                    'fallidos_semana' => (int) ($fallidosSemana[$user->id] ?? 0),
                    'sesiones_abiertas' => $sesion ? (int) $sesion->sesiones : 0,
                    'ultima_actividad' => $sesion
                        ? Carbon::parse($sesion->ultima_actividad)->toIso8601String()
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'totales' => [
                    'ingresos_hoy' => AuditLog::where('evento', 'login')->where('created_at', '>=', $hoy)->count(),
                    'fallidos_24h' => AuditLog::where('evento', 'login_fallido')->where('created_at', '>=', $ultimas24)->count(),
                    'cambios_usuarios_semana' => AuditLog::where('categoria', 'usuarios')->where('created_at', '>=', $semana)->count(),
                    'usuarios_conectados' => $sesionesVivas->count(),
                ],
                'usuarios' => $usuarios,
            ],
        ], 200);
    }

    /**
     * @return \Illuminate\Support\Collection<int|string, mixed>
     */
    private function contarPorUsuario(string $evento, Carbon $desde)
    {
        return AuditLog::query()
            ->whereNotNull('user_id')
            ->where('evento', $evento)
            ->where('created_at', '>=', $desde)
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'user_id');
    }

    /**
     * Los tokens que todavía autentican, agrupados por usuario.
     *
     * El mismo criterio que VencimientoDeSesion: un token vive mientras su
     * última actividad —o su creación, si no se ha usado— esté dentro del plazo
     * de inactividad.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function sesionesVivas()
    {
        $query = DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->groupBy('tokenable_id')
            ->select(
                'tokenable_id',
                DB::raw('COUNT(*) as sesiones'),
                DB::raw('MAX(COALESCE(last_used_at, created_at)) as ultima_actividad')
            );

        $minutos = VencimientoDeSesion::minutos();

        if ($minutos > 0) {
            $query->whereRaw('COALESCE(last_used_at, created_at) > ?', [now()->subMinutes($minutos)]);
        }

        return $query->get()->keyBy('tokenable_id');
    }
}
