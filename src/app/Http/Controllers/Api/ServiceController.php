<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use App\Support\Listados\Pagina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El catálogo de servicios y sus tarifas.
 *
 * Leerlo lo puede hacer cualquiera que cobre —el selector del formulario de
 * facturación se llena con esto—; mantenerlo, solo el administrador: un precio
 * no se corrige desde el mostrador mientras se atiende a alguien.
 */
class ServiceController extends Controller
{
    /**
     * Listar el catálogo.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::select('id', 'nombre', 'descripcion', 'precio', 'activo');

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->has('activo') && $request->input('activo') !== '') {
            $query->where('activo', filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(
            Pagina::respuesta($request, $query->orderBy('nombre')),
            200
        );
    }

    /**
     * Agregar un servicio al catálogo.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $service = Service::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'precio' => $validated['precio'],
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Servicio agregado al catálogo.',
            'data' => $service,
        ], 201);
    }

    /**
     * Corregir un servicio.
     */
    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Servicio actualizado.',
            'data' => $service,
        ], 200);
    }

    /**
     * Retirar un servicio del catálogo.
     *
     * Baja lógica, como los pacientes y los usuarios: el servicio desaparece
     * del selector, pero los recibos que lo cobraron siguen explicándose. Y si
     * vuelve a prestarse, se reactiva en lugar de crearlo otra vez y tropezar
     * con que el nombre ya existe.
     */
    public function destroy(Service $service): JsonResponse
    {
        $service->update(['activo' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Servicio retirado del catálogo.',
            'data' => $service,
        ], 200);
    }
}
