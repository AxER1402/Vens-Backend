<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajuste\UpdateWhatsAppRequest;
use App\Support\Ajustes\Ajustes;
use App\Support\WhatsApp\WhatsApp;
use Illuminate\Http\JsonResponse;

/**
 * Los recordatorios de citas por WhatsApp.
 *
 * Dos cosas distintas que la pantalla muestra juntas: si los recordatorios
 * están encendidos (un ajuste de la clínica) y si hay un teléfono vinculado
 * que pueda mandarlos (el estado del servicio de WhatsApp Web). Se puede
 * encender sin teléfono vinculado; simplemente no saldrá nada hasta que se
 * escanee el QR, y la pantalla lo avisa.
 *
 * Todo es del administrador y del médico, como el resto de la configuración
 * de la agenda: la ruta lo decide.
 */
class WhatsAppController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->cuerpo(),
        ], 200);
    }

    public function update(UpdateWhatsAppRequest $request): JsonResponse
    {
        $activos = $request->boolean('recordatorios');

        Ajustes::guardar(['whatsapp.recordatorios' => $activos]);

        return response()->json([
            'success' => true,
            'message' => $activos
                ? 'Los recordatorios por WhatsApp quedaron activados.'
                : 'Los recordatorios por WhatsApp quedaron desactivados.',
            'data' => $this->cuerpo(),
        ], 200);
    }

    /**
     * Desvincular el teléfono de la clínica, para vincular otro.
     */
    public function cerrarSesion(): JsonResponse
    {
        WhatsApp::cerrarSesion();

        return response()->json([
            'success' => true,
            'message' => 'Se desvinculó el teléfono. Escanee el código nuevo para vincular otro.',
            'data' => $this->cuerpo(),
        ], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function cuerpo(): array
    {
        return [
            'recordatorios' => (bool) Ajustes::obtener('whatsapp.recordatorios'),
            'conexion' => WhatsApp::estado(),
        ];
    }
}
