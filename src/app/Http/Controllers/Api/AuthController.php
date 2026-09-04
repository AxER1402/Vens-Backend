<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

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

        // Crear token API con Sanctum. La duración de la sesión la fija
        // config/sanctum.php ('expiration'), una hora contada desde este
        // momento, y se le informa al cliente para que pueda cerrar la sesión
        // en la interfaz apenas se cumpla el plazo.
        $tokenNuevo = $user->createToken('auth_token');
        $expiracion = $this->expiracionDelToken($tokenNuevo->accessToken);

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'access_token' => $tokenNuevo->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => $expiracion['expires_in'],
                'expires_at' => $expiracion['expires_at'],
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
     * Enviar el enlace de restablecimiento de contraseña al correo del usuario.
     *
     * Siempre se responde con el mismo mensaje genérico, exista o no la cuenta,
     * para evitar que el endpoint sirva para enumerar los correos registrados.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $respuestaGenerica = response()->json([
            'success' => true,
            'message' => 'Si el correo está registrado, recibirá un enlace para restablecer su contraseña.',
        ], 200);

        $user = User::where('email', $request->email)->first();

        // No se envía el enlace a cuentas inexistentes ni inactivas, igual que
        // el login rechaza a los usuarios dados de baja.
        if (! $user || ! $user->activo) {
            return $respuestaGenerica;
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'message' => 'Ya se envió un enlace recientemente. Espere un momento antes de volver a intentarlo.',
            ], 429);
        }

        return $respuestaGenerica;
    }

    /**
     * Restablecer la contraseña a partir del token enviado por correo.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Revocar los tokens de Sanctum para cerrar las sesiones
                // abiertas con la contraseña anterior.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'El token de restablecimiento es inválido o ha expirado.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'La contraseña se restableció correctamente. Ya puede iniciar sesión.',
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
        $expiracion = $this->expiracionDelToken($user->currentAccessToken());

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
                'expires_in' => $expiracion['expires_in'],
                'expires_at' => $expiracion['expires_at'],
            ],
        ], 200);
    }

    /**
     * Calcular cuánto le queda de vida al token con el que se hizo la petición.
     *
     * Sanctum no guarda la fecha de vencimiento en la base de datos cuando la
     * duración se define por configuración: la calcula sobre la fecha de
     * creación del token. Aquí se hace la misma cuenta para poder devolverla.
     *
     * @return array{expires_in: int|null, expires_at: string|null}
     */
    private function expiracionDelToken(mixed $token): array
    {
        $minutos = (int) config('sanctum.expiration');

        // Las sesiones de primera parte (cookie) no llevan token personal y
        // tampoco vencen por esta vía.
        if ($minutos <= 0 || ! $token instanceof PersonalAccessToken) {
            return ['expires_in' => null, 'expires_at' => null];
        }

        $vence = $token->created_at->copy()->addMinutes($minutos);
        // Se trunca hacia abajo para no prometer más tiempo del que queda.
        $restante = max(0, (int) now()->diffInSeconds($vence, false));

        return [
            'expires_in' => (int) $restante,
            'expires_at' => $vence->toIso8601String(),
        ];
    }
}
