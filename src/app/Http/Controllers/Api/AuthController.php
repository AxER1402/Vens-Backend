<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Iniciar sesión y retornar token Bearer de autenticación.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        if (! $user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario se encuentra inactivo. Comuníquese con el administrador.',
            ], 403);
        }

        // Crear token API con Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'rol' => $user->rol,
                    'activo' => $user->activo,
                    'telefono' => $user->telefono,
                ],
            ],
        ], 200);
    }

    /**
     * Cerrar sesión y revocar el token actual del usuario.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $request->user()?->currentAccessToken();
        
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente.',
        ], 200);
    }

    /**
     * Obtener los datos del usuario autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'activo' => $user->activo,
                'telefono' => $user->telefono,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ],
        ], 200);
    }
}
