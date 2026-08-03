<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\AssignPatientRequest;
use App\Http\Requests\Appointment\CancelAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    /**
     * Listar citas con control multianual, mensual, por día, paciente, médico y estado.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::with([
            'patient',
            'medico:id,name,email,rol',
            'creator:id,name,email',
        ]);

        // Control temporal: Filtro por Año (ej: year=2026)
        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            if ($request->filled('month')) {
                $month = (int) $request->input('month');
                $query->byMonth($year, $month);
            } else {
                $query->byYear($year);
            }
        }

        // Filtro por fecha exacta (ej: date=2026-08-15)
        if ($request->filled('date')) {
            $query->byDate($request->input('date'));
        }

        // Filtro por rango de fechas (from_date y to_date)
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->byDateRange($request->input('from_date'), $request->input('to_date'));
        }

        // Filtro por paciente existente
        if ($request->filled('patient_id')) {
            $query->byPatient((int) $request->input('patient_id'));
        }

        // Filtro por médico asignado
        if ($request->filled('medico_id')) {
            $query->byMedico((int) $request->input('medico_id'));
        }

        // Filtro por estado (Programada, Completada, Reagendada, Cancelada, No Asistió)
        if ($request->filled('estado')) {
            $query->byEstado($request->input('estado'));
        }

        // Búsqueda libre por nombre de paciente o motivo de consulta
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('motivo', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $pq->where('nombre', 'like', "%{$search}%")
                         ->orWhere('telefono', 'like', "%{$search}%");
                  });
            });
        }

        // Orden predeterminado: por fecha de inicio ascendente (ideal para agendas)
        $appointments = $query->orderBy('fecha_hora_inicio', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ], 200);
    }

    /**
     * Consultar detalle completo de una cita específica.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load([
            'patient',
            'medico:id,name,email,rol',
            'creator:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'data' => $appointment,
        ], 200);
    }

    /**
     * Agendar una nueva cita médica.
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $medicoId = $validated['medico_id'] ?? null;
        $inicio = $validated['fecha_hora_inicio'];
        $fin = $validated['fecha_hora_fin'];

        // Verificación de choque de horarios para el médico si está asignado
        if ($medicoId && !$request->boolean('ignore_conflict')) {
            $conflict = $this->checkScheduleConflict($medicoId, $inicio, $fin);
            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'El médico ya tiene una cita agendada en el horario seleccionado.',
                    'conflict_appointment' => $conflict,
                ], 422);
            }
        }

        $appointment = Appointment::create([
            'patient_id' => $validated['patient_id'],
            'medico_id' => $medicoId,
            'created_by' => auth()->id(),
            'fecha_hora_inicio' => $inicio,
            'fecha_hora_fin' => $fin,
            'motivo' => $validated['motivo'],
            'estado' => 'Programada',
            'notas' => $validated['notas'] ?? null,
        ]);

        $appointment->load(['patient', 'medico:id,name,email,rol', 'creator:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Cita médica agendada exitosamente.',
            'data' => $appointment,
        ], 201);
    }

    /**
     * Editar o Reagendar una cita existente.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validated();

        $medicoId = $validated['medico_id'] ?? $appointment->medico_id;
        $inicio = $validated['fecha_hora_inicio'] ?? $appointment->fecha_hora_inicio;
        $fin = $validated['fecha_hora_fin'] ?? $appointment->fecha_hora_fin;

        // Si se cambia fecha/hora o médico, verificar conflictos
        if ($medicoId && ($inicio != $appointment->fecha_hora_inicio || $fin != $appointment->fecha_hora_fin || $medicoId != $appointment->medico_id)) {
            if (!$request->boolean('ignore_conflict')) {
                $conflict = $this->checkScheduleConflict($medicoId, $inicio, $fin, $appointment->id);
                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El horario seleccionado entra en conflicto con otra cita del médico.',
                        'conflict_appointment' => $conflict,
                    ], 422);
                }
            }
            
            // Si la cita estaba Programada y cambian las fechas, se actualiza automáticamente el estado a Reagendada si no se especifica otro
            if (!isset($validated['estado']) && $appointment->estado === 'Programada') {
                $validated['estado'] = 'Reagendada';
            }
        }

        $appointment->update($validated);
        $appointment->load(['patient', 'medico:id,name,email,rol', 'creator:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Cita actualizada exitosamente.',
            'data' => $appointment,
        ], 200);
    }

    /**
     * Asignar o cambiar el paciente de una cita existente.
     */
    public function assignPatient(AssignPatientRequest $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validated();

        $patient = Patient::findOrFail($validated['patient_id']);

        $appointment->update([
            'patient_id' => $patient->id,
        ]);

        $appointment->load(['patient', 'medico:id,name,email,rol', 'creator:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => "Paciente '{$patient->nombre}' asignado a la cita exitosamente.",
            'data' => $appointment,
        ], 200);
    }

    /**
     * Cancelar una cita médica.
     */
    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validated();

        $appointment->update([
            'estado' => 'Cancelada',
            'motivo_cancelacion' => $validated['motivo_cancelacion'] ?? 'Cancelada por el usuario o clínica',
        ]);

        $appointment->load(['patient', 'medico:id,name,email,rol', 'creator:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Cita cancelada exitosamente.',
            'data' => $appointment,
        ], 200);
    }

    /**
     * Auxiliar para verificar solapamiento de horarios de un médico.
     */
    private function checkScheduleConflict(int $medicoId, string $inicio, string $fin, ?int $excludeAppointmentId = null): ?Appointment
    {
        $query = Appointment::where('medico_id', $medicoId)
            ->where('estado', '!=', 'Cancelada')
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fecha_hora_inicio', [$inicio, $fin])
                  ->orWhereBetween('fecha_hora_fin', [$inicio, $fin])
                  ->orWhere(function ($sub) use ($inicio, $fin) {
                      $sub->where('fecha_hora_inicio', '<=', $inicio)
                          ->where('fecha_hora_fin', '>=', $fin);
                  });
            });

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->first();
    }
}
