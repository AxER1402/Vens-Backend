<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationDismissal;
use App\Support\Notificaciones\AvisosDeAgenda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Avisos pendientes del usuario: los pacientes que vienen en lo que queda
     * de hoy y mañana, sin los que ya descartó a mano.
     */
    public function index(Request $request): JsonResponse
    {
        $agenda = new AvisosDeAgenda($request->user());
        $avisos = $agenda->avisos();
        [$desde, $hasta] = $agenda->ventana();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $avisos->count(),
                'ventana' => [
                    'desde' => $desde->toIso8601String(),
                    'hasta' => $hasta->toIso8601String(),
                ],
                'notificaciones' => $avisos,
            ],
        ], 200);
    }

    /**
     * Descartar un aviso. No borra la cita: solo deja constancia de que este
     * usuario no quiere volver a verlo.
     */
    public function destroy(Request $request, string $clave): JsonResponse
    {
        $usuario = $request->user();

        NotificationDismissal::updateOrCreate(
            ['user_id' => $usuario->id, 'clave' => $clave],
            ['descartada_at' => now()],
        );

        $this->limpiarDescartesViejos($usuario->id);

        return response()->json([
            'success' => true,
            'message' => 'Aviso descartado.',
        ], 200);
    }

    /**
     * Descartar de una vez todos los avisos que el usuario tiene a la vista.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $avisos = (new AvisosDeAgenda($usuario))->avisos();

        foreach ($avisos as $aviso) {
            NotificationDismissal::updateOrCreate(
                ['user_id' => $usuario->id, 'clave' => $aviso['clave']],
                ['descartada_at' => now()],
            );
        }

        $this->limpiarDescartesViejos($usuario->id);

        return response()->json([
            'success' => true,
            'message' => 'Se descartaron los avisos pendientes.',
            'data' => ['descartados' => $avisos->count()],
        ], 200);
    }

    /**
     * Un descarte deja de servir en cuanto la cita a la que apunta queda atrás,
     * y la ventana de avisos no pasa de dos días. Se limpian aquí, aprovechando
     * el viaje, para no tener que programar una tarea que barra la tabla.
     */
    private function limpiarDescartesViejos(int $userId): void
    {
        NotificationDismissal::query()
            ->where('user_id', $userId)
            ->where('descartada_at', '<', now()->subWeek())
            ->delete();
    }
}
