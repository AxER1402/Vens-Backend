<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios del sistema.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'rol', 'activo', 'telefono', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ], 200);
    }

    /**
     * Obtener el detalle de un usuario específico.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'activo' => $user->activo,
                'telefono' => $user->telefono,
                'created_at' => $user->created_at,
            ],
        ], 200);
    }

    /**
     * Crear un nuevo usuario en el sistema.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'rol' => $validated['rol'],
            'telefono' => $validated['telefono'] ?? null,
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'activo' => $user->activo,
                'telefono' => $user->telefono,
                'created_at' => $user->created_at,
            ],
        ], 201);
    }

    /**
     * Actualizar los datos de un usuario existente.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        // Si se envió una nueva contraseña, la encriptamos
        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'activo' => $user->activo,
                'telefono' => $user->telefono,
                'updated_at' => $user->updated_at,
            ],
        ], 200);
    }

    /**
     * Desactivar un usuario (desactivación lógica por control y seguridad en lugar de eliminar el registro).
     */
    public function destroy(User $user): JsonResponse
    {
        $user->update(['activo' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario desactivado exitosamente.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'activo' => $user->activo,
            ],
        ], 200);
    }
}
