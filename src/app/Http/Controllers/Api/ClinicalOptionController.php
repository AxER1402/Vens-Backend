<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicalOptionController extends Controller
{
    /**
     * Entregar el catálogo de opciones clínicas agrupado por categoría, para que
     * el formulario de historia clínica pueda construir sus listas desde la API.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClinicalOption::activas();

        if ($request->filled('categoria')) {
            $query->byCategoria($request->input('categoria'));
        }

        $opciones = $query->orderBy('categoria')
            ->orderBy('orden')
            ->get()
            ->groupBy('categoria')
            ->map(fn ($grupo) => $grupo->map(fn (ClinicalOption $opcion) => [
                'id' => $opcion->id,
                'valor' => $opcion->valor,
                'etiqueta' => $opcion->etiqueta ?? $opcion->valor,
            ])->values());

        return response()->json([
            'success' => true,
            'data' => $opciones,
        ], 200);
    }
}
