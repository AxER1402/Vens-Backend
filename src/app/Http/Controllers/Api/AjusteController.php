<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajuste\StoreLogoRequest;
use App\Http\Requests\Ajuste\UpdateAjustesRequest;
use App\Support\Ajustes\Ajustes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Los datos de la clínica.
 *
 * Leerlos lo puede hacer cualquiera que haya iniciado sesión, porque la
 * aplicación los necesita para trabajar: la pantalla de facturación pinta la
 * moneda y el IVA. Cambiarlos es solo del administrador, y eso lo decide la
 * ruta, no este controlador.
 */
class AjusteController extends Controller
{
    /** Carpeta del logo dentro del disco público. */
    private const CARPETA = 'ajustes';

    /**
     * Los ajustes vigentes.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->cuerpo(),
        ], 200);
    }

    /**
     * Guardar los datos de la clínica.
     */
    public function update(UpdateAjustesRequest $request): JsonResponse
    {
        // El formulario manda los campos agrupados ({clinica: {nombre: …}})
        // porque así se validan; en la tabla la clave es 'clinica.nombre'.
        Ajustes::guardar(Arr::dot($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Los datos de la clínica se actualizaron.',
            'data' => $this->cuerpo(),
        ], 200);
    }

    /**
     * Subir el logo del membrete.
     */
    public function storeLogo(StoreLogoRequest $request): JsonResponse
    {
        $anterior = Ajustes::todos()['clinica.logo'] ?? null;

        $ruta = $request->file('logo')->store(self::CARPETA, 'public');

        // Se guarda 'storage/ajustes/loquesea.png' y no la ruta del disco:
        // public/storage es un enlace a storage/app/public, así que el
        // generador de PDF lo resuelve con el mismo public_path() de siempre
        // y no hay que tocarlo.
        Ajustes::guardar(['clinica.logo' => 'storage/'.$ruta]);

        // El anterior se borra después, y solo si era uno subido: el de
        // fábrica (img/isotipo.png) viene con el repositorio y no es nuestro
        // para borrarlo.
        if ($anterior && str_starts_with($anterior, 'storage/'.self::CARPETA.'/')) {
            Storage::disk('public')->delete(substr($anterior, strlen('storage/')));
        }

        return response()->json([
            'success' => true,
            'message' => 'El logo se actualizó.',
            'data' => $this->cuerpo(),
        ], 200);
    }

    /**
     * Los ajustes más lo que la pantalla necesita para pintarlos: la URL con
     * la que se ve el logo, que no es la ruta con la que se guarda.
     *
     * @return array<string, mixed>
     */
    private function cuerpo(): array
    {
        $valores = Ajustes::todos();
        $logo = $valores['clinica.logo'] ?? null;

        return [
            'ajustes' => $valores,
            'logo_url' => $logo ? asset($logo) : null,
        ];
    }
}
