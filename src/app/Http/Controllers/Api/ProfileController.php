<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreFotoRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * La cuenta propia.
 *
 * Todo lo de aquí opera sobre el usuario autenticado y nunca recibe un id por
 * la ruta. Es la diferencia con UserController: aquel administra las cuentas
 * ajenas y está restringido al administrador; este es el autoservicio, y por
 * eso lo puede usar cualquiera que haya iniciado sesión.
 */
class ProfileController extends Controller
{
    /** Carpeta de las fotos dentro del disco público. */
    private const CARPETA = 'avatares';

    /**
     * Actualizar el nombre y el teléfono propios.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->respuesta($user, 'Sus datos se actualizaron correctamente.');
    }

    /**
     * Cambiar la contraseña propia.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('password_actual'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual no es correcta.',
                'errors' => [
                    'password_actual' => ['La contraseña actual no es correcta.'],
                ],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        // Las demás sesiones se cierran. Si la contraseña se cambió justamente
        // porque alguien más la sabía, dejar vivos los tokens que esa persona
        // ya tenía haría inútil el cambio. Se conserva el token con el que
        // llegó esta petición para no expulsar a quien la acaba de cambiar.
        $actual = $request->user()->currentAccessToken();

        $user->tokens()
            ->when(
                $actual instanceof PersonalAccessToken,
                fn ($query) => $query->where('id', '!=', $actual->getKey())
            )
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Su contraseña se actualizó correctamente.',
        ], 200);
    }

    /**
     * Subir o reemplazar la foto de perfil.
     */
    public function storeFoto(StoreFotoRequest $request): JsonResponse
    {
        $user = $request->user();
        $anterior = $user->foto_path;

        $ruta = $request->file('foto')->store(self::CARPETA, 'public');

        $user->update(['foto_path' => $ruta]);

        // La anterior se borra después de guardar la nueva y no antes: si algo
        // falla a media subida, el usuario se queda con la foto que ya tenía en
        // lugar de quedarse sin ninguna.
        if ($anterior && $anterior !== $ruta) {
            Storage::disk('public')->delete($anterior);
        }

        return $this->respuesta($user, 'Su foto de perfil se actualizó.');
    }

    /**
     * Quitar la foto de perfil y volver a las iniciales.
     */
    public function destroyFoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->foto_path) {
            Storage::disk('public')->delete($user->foto_path);
            $user->update(['foto_path' => null]);
        }

        return $this->respuesta($user, 'Su foto de perfil se quitó.');
    }

    /**
     * El mismo cuerpo que devuelve /auth/me, para que la pantalla pueda
     * refrescar la sesión con lo que responde cualquiera de estas acciones.
     */
    private function respuesta(User $user, string $mensaje): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $mensaje,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'activo' => $user->activo,
                'telefono' => $user->telefono,
                'foto_url' => $user->foto_url,
            ],
        ], 200);
    }
}
