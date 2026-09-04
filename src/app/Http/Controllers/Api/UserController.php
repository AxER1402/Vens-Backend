<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Support\Contacto\Telefono;
use App\Support\Listados\Pagina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Listar los usuarios del sistema.
     *
     * Los filtros se aplican aquí y no en la pantalla porque el listado se
     * pagina: filtrando en el navegador, la búsqueda solo miraría los treinta
     * de la página abierta y diría que no hay nadie que sí existe en la
     * siguiente.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::select('id', 'name', 'email', 'rol', 'activo', 'telefono', 'created_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $digitos = Telefono::normalizar($search);

            $query->where(function ($q) use ($search, $digitos) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");

                if ($digitos !== null) {
                    $q->orWhere('telefono', 'like', "%{$digitos}%");
                }
            });
        }

        if ($request->filled('rol')) {
            $query->where('rol', $request->input('rol'));
        }

        if ($request->has('activo') && $request->input('activo') !== '') {
            $query->where('activo', filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(
            Pagina::respuesta($request, $query->orderBy('created_at', 'desc')),
            200
        );
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
