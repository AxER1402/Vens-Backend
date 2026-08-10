<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DopplerReport\StoreDopplerReportRequest;
use App\Http\Requests\DopplerReport\UpdateDopplerReportRequest;
use App\Models\ClinicalHistory;
use App\Models\DopplerReport;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DopplerReportController extends Controller
{
    /**
     * Relaciones que acompañan siempre a un reporte en la respuesta.
     *
     * @var array<int, string>
     */
    private const RELACIONES = [
        'patient',
        'creator:id,name,email',
        'editor:id,name,email',
    ];

    /**
     * Listar reportes de Ecodöppler con filtros por paciente, consulta,
     * estado y rango de fechas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DopplerReport::with(self::RELACIONES);

        if ($request->filled('patient_id')) {
            $query->byPatient((int) $request->input('patient_id'));
        }

        if ($request->filled('clinical_history_id')) {
            $query->byClinicalHistory((int) $request->input('clinical_history_id'));
        }

        if ($request->filled('estado_registro')) {
            $query->byEstadoRegistro($request->input('estado_registro'));
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->byDateRange($request->input('from_date'), $request->input('to_date'));
        }

        // Filtro por activación lógica (por defecto solo se listan los vigentes)
        $query->where('activo', $request->has('activo')
            ? filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN)
            : true);

        // Búsqueda libre por la conclusión o por nombre/teléfono del paciente
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('conclusion', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $pq->where('nombre', 'like', "%{$search}%")
                         ->orWhere('telefono', 'like', "%{$search}%");
                  });
            });
        }

        $reports = $query->orderBy('fecha_estudio', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reports,
        ], 200);
    }

    /**
     * Consultar el detalle completo de un reporte de Ecodöppler.
     */
    public function show(DopplerReport $dopplerReport): JsonResponse
    {
        $dopplerReport->load(self::RELACIONES);

        return response()->json([
            'success' => true,
            'data' => $dopplerReport,
        ], 200);
    }

    /**
     * Listar los estudios de un paciente, del más reciente al más antiguo.
     */
    public function byPatient(Request $request, Patient $patient): JsonResponse
    {
        $reports = $this->listarVigentes(
            DopplerReport::with(self::RELACIONES)->byPatient($patient->id),
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $reports,
        ], 200);
    }

    /**
     * Listar los estudios adjuntos a una consulta del expediente. Es la consulta
     * que usa el formulario para saber si el reporte ya existe y debe editarse.
     */
    public function byClinicalHistory(Request $request, ClinicalHistory $clinicalHistory): JsonResponse
    {
        $reports = $this->listarVigentes(
            DopplerReport::with(self::RELACIONES)->byClinicalHistory($clinicalHistory->id),
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $reports,
        ], 200);
    }

    /**
     * Registrar un nuevo reporte de Ecodöppler.
     */
    public function store(StoreDopplerReportRequest $request): JsonResponse
    {
        $dopplerReport = DopplerReport::create(array_merge($request->validated(), [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]));

        $dopplerReport->load(self::RELACIONES);

        return response()->json([
            'success' => true,
            'message' => $dopplerReport->estado_registro === 'Finalizada'
                ? 'Reporte de Ecodöppler registrado exitosamente.'
                : 'Borrador del reporte de Ecodöppler guardado exitosamente.',
            'data' => $dopplerReport,
        ], 201);
    }

    /**
     * Actualizar un reporte de Ecodöppler existente.
     */
    public function update(UpdateDopplerReportRequest $request, DopplerReport $dopplerReport): JsonResponse
    {
        $dopplerReport->update(array_merge($request->validated(), [
            'updated_by' => auth()->id(),
        ]));

        $dopplerReport->refresh()->load(self::RELACIONES);

        return response()->json([
            'success' => true,
            'message' => $dopplerReport->estado_registro === 'Finalizada'
                ? 'Reporte de Ecodöppler actualizado exitosamente.'
                : 'Borrador del reporte de Ecodöppler actualizado exitosamente.',
            'data' => $dopplerReport,
        ], 200);
    }

    /**
     * Desactivar un reporte (desactivación lógica: el estudio forma parte del
     * expediente clínico y nunca se elimina físicamente).
     */
    public function destroy(DopplerReport $dopplerReport): JsonResponse
    {
        $dopplerReport->update([
            'activo' => false,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reporte de Ecodöppler desactivado exitosamente.',
            'data' => [
                'id' => $dopplerReport->id,
                'patient_id' => $dopplerReport->patient_id,
                'clinical_history_id' => $dopplerReport->clinical_history_id,
                'activo' => $dopplerReport->activo,
                'estado_registro' => $dopplerReport->estado_registro,
            ],
        ], 200);
    }

    /**
     * Aplicar los filtros comunes de los listados por paciente y por consulta.
     *
     * @param  Builder<DopplerReport>  $query
     * @return Collection<int, DopplerReport>
     */
    private function listarVigentes(Builder $query, Request $request): Collection
    {
        if ($request->filled('estado_registro')) {
            $query->byEstadoRegistro($request->input('estado_registro'));
        }

        $query->where('activo', $request->has('activo')
            ? filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN)
            : true);

        return $query->orderBy('fecha_estudio', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
