<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * Listar todos los pacientes con opción a filtros de búsqueda, estado y estado de activación.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Patient::query();

        // Filtro por término de búsqueda (nombre o teléfono)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        // Filtro por estado del paciente (Activo, Seguimiento, Alta)
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        // Filtro por activación lógica (activo = true/false)
        if ($request->has('activo')) {
            $query->where('activo', filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        $patients = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $patients,
        ], 200);
    }

    /**
     * Obtener el detalle de un paciente específico.
     */
    public function show(Patient $patient): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $patient,
        ], 200);
    }

    /**
     * Registrar un nuevo paciente en la clínica.
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $patient = Patient::create([
            'nombre' => $validated['nombre'],
            'edad' => $validated['edad'],
            'telefono' => $validated['telefono'],
            'lugar_residencia' => $validated['lugar_residencia'],
            'estado_civil' => $validated['estado_civil'],
            'religion' => $validated['religion'] ?? null,
            'estado' => $validated['estado'] ?? 'Activo',
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paciente registrado exitosamente.',
            'data' => $patient,
        ], 201);
    }

    /**
     * Actualizar los datos de un paciente existente.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $validated = $request->validated();

        $patient->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Información del paciente actualizada exitosamente.',
            'data' => $patient->fresh(),
        ], 200);
    }

    /**
     * Desactivar un paciente (desactivación lógica para preservar su historial clínico sin eliminar el registro).
     */
    public function destroy(Patient $patient): JsonResponse
    {
        $patient->update(['activo' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Paciente desactivado exitosamente.',
            'data' => [
                'id' => $patient->id,
                'nombre' => $patient->nombre,
                'activo' => $patient->activo,
                'estado' => $patient->estado,
            ],
        ], 200);
    }

    /**
     * Alternar o cambiar explícitamente el estado de activación lógica del paciente.
     */
    public function toggleActive(Request $request, Patient $patient): JsonResponse
    {
        $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $patient->update([
            'activo' => $request->boolean('activo'),
        ]);

        $accion = $patient->activo ? 'activado' : 'desactivado';

        return response()->json([
            'success' => true,
            'message' => "Paciente {$accion} exitosamente.",
            'data' => [
                'id' => $patient->id,
                'nombre' => $patient->nombre,
                'activo' => $patient->activo,
                'estado' => $patient->estado,
            ],
        ], 200);
    }
}
