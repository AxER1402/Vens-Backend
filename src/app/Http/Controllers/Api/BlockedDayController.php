<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlockedDay\StoreBlockedDayRequest;
use App\Http\Requests\BlockedDay\UpdateBlockedDayRequest;
use App\Models\Appointment;
use App\Models\BlockedDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockedDayController extends Controller
{
    /**
     * Listar días bloqueados (feriados, vacaciones y cierres de la clínica).
     */
    public function index(Request $request): JsonResponse
    {
        $query = BlockedDay::with('creator:id,name,email');

        // Bloqueos que tocan el rango visible de la agenda (from_date / to_date)
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->overlapping($request->input('from_date'), $request->input('to_date'));
        }

        // Bloqueos que cubren un día específico
        if ($request->filled('date')) {
            $query->coveringDate($request->input('date'));
        }

        // Bloqueos de un año determinado
        if ($request->filled('year')) {
            $query->byYear((int) $request->input('year'));
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        $blockedDays = $query->orderBy('fecha_inicio', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $blockedDays,
        ], 200);
    }

    /**
     * Registrar un nuevo bloqueo de la agenda (un día suelto o un rango).
     */
    public function store(StoreBlockedDayRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $blockedDay = BlockedDay::create([
            'created_by' => auth()->id(),
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'motivo' => $validated['motivo'],
            'tipo' => $validated['tipo'] ?? 'Feriado',
        ]);

        $blockedDay->load('creator:id,name,email');

        // Las citas ya agendadas no se tocan: se informa cuántas quedaron dentro
        // del bloqueo para que la clínica decida reagendarlas o cancelarlas.
        $citasAfectadas = $this->countAppointmentsInRange(
            $validated['fecha_inicio'],
            $validated['fecha_fin']
        );

        return response()->json([
            'success' => true,
            'message' => 'Bloqueo de agenda registrado exitosamente.',
            'data' => $blockedDay,
            'citas_afectadas' => $citasAfectadas,
        ], 201);
    }

    /**
     * Editar un bloqueo existente.
     */
    public function update(UpdateBlockedDayRequest $request, BlockedDay $blockedDay): JsonResponse
    {
        $validated = $request->validated();

        // Al cambiar solo un extremo del rango, se valida contra el valor guardado
        $inicio = $validated['fecha_inicio'] ?? $blockedDay->fecha_inicio->format('Y-m-d');
        $fin = $validated['fecha_fin'] ?? $blockedDay->fecha_fin->format('Y-m-d');

        if ($fin < $inicio) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            ], 422);
        }

        $blockedDay->update($validated);
        $blockedDay->load('creator:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Bloqueo de agenda actualizado exitosamente.',
            'data' => $blockedDay,
            'citas_afectadas' => $this->countAppointmentsInRange($inicio, $fin),
        ], 200);
    }

    /**
     * Eliminar un bloqueo y volver a habilitar esas fechas.
     */
    public function destroy(BlockedDay $blockedDay): JsonResponse
    {
        $blockedDay->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bloqueo eliminado. Las fechas quedan disponibles para agendar.',
        ], 200);
    }

    /**
     * Citas activas ya agendadas dentro de un rango de fechas bloqueado.
     */
    private function countAppointmentsInRange(string $inicio, string $fin): int
    {
        return Appointment::where('estado', '!=', 'Cancelada')
            ->whereDate('fecha_hora_inicio', '>=', $inicio)
            ->whereDate('fecha_hora_inicio', '<=', $fin)
            ->count();
    }
}
