<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalHistory\StoreClinicalHistoryRequest;
use App\Http\Requests\ClinicalHistory\StoreVenousMapRequest;
use App\Http\Requests\ClinicalHistory\UpdateClinicalHistoryRequest;
use App\Models\ClinicalHistory;
use App\Models\ClinicalOption;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClinicalHistoryController extends Controller
{
    /**
     * Firma binaria que debe tener todo archivo PNG válido.
     */
    private const FIRMA_PNG = "\x89PNG\r\n\x1a\n";

    /**
     * Relaciones que acompañan siempre a una historia clínica en la respuesta.
     *
     * @var array<int, string>
     */
    private const RELACIONES = [
        'patient',
        'options',
        'creator:id,name,email',
        'editor:id,name,email',
    ];

    /**
     * Listar historias clínicas con filtros por paciente, estado y rango de fechas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClinicalHistory::with(self::RELACIONES);

        // Filtro por paciente
        if ($request->filled('patient_id')) {
            $query->byPatient((int) $request->input('patient_id'));
        }

        // Filtro por estado del registro (Borrador, Finalizada)
        if ($request->filled('estado_registro')) {
            $query->byEstadoRegistro($request->input('estado_registro'));
        }

        // Filtro por rango de fechas de consulta
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->byDateRange($request->input('from_date'), $request->input('to_date'));
        }

        // Filtro por activación lógica (por defecto solo se listan las vigentes)
        $query->where('activo', $request->has('activo')
            ? filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN)
            : true);

        // Búsqueda libre por notas o por nombre/teléfono del paciente
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('notas', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $pq->where('nombre', 'like', "%{$search}%")
                         ->orWhere('telefono', 'like', "%{$search}%");
                  });
            });
        }

        $histories = $query->orderBy('fecha_consulta', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $histories,
        ], 200);
    }

    /**
     * Consultar el detalle completo de una historia clínica.
     */
    public function show(ClinicalHistory $clinicalHistory): JsonResponse
    {
        $clinicalHistory->load(self::RELACIONES);

        return response()->json([
            'success' => true,
            'data' => $clinicalHistory,
        ], 200);
    }

    /**
     * Listar las historias clínicas de un paciente, de la más reciente a la más antigua.
     */
    public function byPatient(Request $request, Patient $patient): JsonResponse
    {
        $query = ClinicalHistory::with(self::RELACIONES)->byPatient($patient->id);

        if ($request->filled('estado_registro')) {
            $query->byEstadoRegistro($request->input('estado_registro'));
        }

        $query->where('activo', $request->has('activo')
            ? filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN)
            : true);

        $histories = $query->orderBy('fecha_consulta', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $histories,
        ], 200);
    }

    /**
     * Registrar una nueva historia clínica (borrador o finalizada).
     */
    public function store(StoreClinicalHistoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $selecciones = $validated['selecciones'] ?? [];
        unset($validated['selecciones']);

        $clinicalHistory = DB::transaction(function () use ($validated, $selecciones) {
            $clinicalHistory = ClinicalHistory::create(array_merge($validated, [
                'estado_registro' => $validated['estado_registro'] ?? 'Borrador',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]));

            $clinicalHistory->options()->sync($this->resolverOpciones($selecciones));

            return $clinicalHistory;
        });

        $clinicalHistory->load(self::RELACIONES);

        return response()->json([
            'success' => true,
            'message' => $clinicalHistory->estado_registro === 'Finalizada'
                ? 'Historia clínica registrada exitosamente.'
                : 'Borrador de la historia clínica guardado exitosamente.',
            'data' => $clinicalHistory,
        ], 201);
    }

    /**
     * Actualizar una historia clínica existente.
     */
    public function update(UpdateClinicalHistoryRequest $request, ClinicalHistory $clinicalHistory): JsonResponse
    {
        $validated = $request->validated();

        // Si no se envían las listas, se conservan las selecciones actuales
        $selecciones = array_key_exists('selecciones', $validated) ? $validated['selecciones'] : null;
        unset($validated['selecciones']);

        $validated['updated_by'] = auth()->id();

        DB::transaction(function () use ($clinicalHistory, $validated, $selecciones) {
            $clinicalHistory->update($validated);

            if (is_array($selecciones)) {
                $clinicalHistory->options()->sync($this->resolverOpciones($selecciones));
            }
        });

        $clinicalHistory->refresh()->load(self::RELACIONES);

        return response()->json([
            'success' => true,
            'message' => $clinicalHistory->estado_registro === 'Finalizada'
                ? 'Historia clínica actualizada exitosamente.'
                : 'Borrador de la historia clínica actualizado exitosamente.',
            'data' => $clinicalHistory,
        ], 200);
    }

    /**
     * Guardar el mapeo venoso dibujado en el canvas como archivo PNG.
     */
    public function storeVenousMap(StoreVenousMapRequest $request, ClinicalHistory $clinicalHistory): JsonResponse
    {
        $binario = $request->contenidoBinario();

        if ($binario === null || ! str_starts_with($binario, self::FIRMA_PNG)) {
            return response()->json([
                'success' => false,
                'message' => 'El mapeo venoso recibido no es una imagen PNG válida.',
            ], 422);
        }

        if (strlen($binario) > StoreVenousMapRequest::MAX_BYTES) {
            return response()->json([
                'success' => false,
                'message' => 'El mapeo venoso supera el tamaño máximo permitido (5 MB).',
            ], 422);
        }

        $rutaAnterior = $clinicalHistory->mapeo_venoso_path;
        $ruta = "mapeos-venosos/{$clinicalHistory->id}/" . Str::uuid()->toString() . '.png';

        Storage::disk('public')->put($ruta, $binario);

        $clinicalHistory->update([
            'mapeo_venoso_path' => $ruta,
            'mapeo_venoso_updated_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        // El mapeo anterior se descarta solo cuando el nuevo quedó almacenado
        if ($rutaAnterior && $rutaAnterior !== $ruta) {
            Storage::disk('public')->delete($rutaAnterior);
        }

        $clinicalHistory->load(self::RELACIONES);

        return response()->json([
            'success' => true,
            'message' => 'Mapeo venoso guardado exitosamente.',
            'data' => $clinicalHistory,
        ], 200);
    }

    /**
     * Desactivar una historia clínica (desactivación lógica: el expediente
     * clínico nunca se elimina físicamente).
     */
    public function destroy(ClinicalHistory $clinicalHistory): JsonResponse
    {
        $clinicalHistory->update([
            'activo' => false,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Historia clínica desactivada exitosamente.',
            'data' => [
                'id' => $clinicalHistory->id,
                'patient_id' => $clinicalHistory->patient_id,
                'activo' => $clinicalHistory->activo,
                'estado_registro' => $clinicalHistory->estado_registro,
            ],
        ], 200);
    }

    /**
     * Traducir las selecciones enviadas por categoría a los IDs del catálogo.
     *
     * @param  array<string, array<int, string>>  $selecciones
     * @return array<int, int>
     */
    private function resolverOpciones(array $selecciones): array
    {
        $categorias = array_filter(
            $selecciones,
            fn ($valores) => is_array($valores) && $valores !== []
        );

        if ($categorias === []) {
            return [];
        }

        return ClinicalOption::activas()
            ->where(function ($query) use ($categorias) {
                foreach ($categorias as $categoria => $valores) {
                    $query->orWhere(function ($sub) use ($categoria, $valores) {
                        $sub->where('categoria', $categoria)->whereIn('valor', $valores);
                    });
                }
            })
            ->pluck('id')
            ->all();
    }
}
