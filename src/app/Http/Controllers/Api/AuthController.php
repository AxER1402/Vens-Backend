<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Profile\StoreFotoRequest;
use App\Models\User;
use App\Support\Auditoria\Bitacora;
use App\Support\Sesion\VencimientoDeSesion;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthController extends Controller
{
    /** Carpeta de las fotos de perfil, la misma que usa ProfileController. */
    private const CARPETA_FOTOS = 'avatares';

    /**
     * Iniciar sesión y retornar token Bearer de autenticación.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Con cuenta o sin ella: una ráfaga de intentos contra un correo
            // que no existe también es algo que el administrador quiere ver.
            Bitacora::registrar(
                'login_fallido',
                $user
                    ? 'Contraseña incorrecta para '.$user->email.'.'
                    : 'Intento con un correo no registrado: '.$request->email.'.',
                $user,
                autor: $user,
                correo: $request->email,
            );

            return response()->json([
                'success' => false,
                'message' => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        if (! $user->activo) {
            Bitacora::registrar(
                'login_fallido',
                'Intento de una cuenta desactivada: '.$user->email.'.',
                $user,
                autor: $user,
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario se encuentra inactivo. Comuníquese con el administrador.',
            ], 403);
        }

        return $this->emitirSesion($user, $user->name.' inició sesión.');
    }

    /**
     * Iniciar sesión con una cuenta de Google.
     *
     * El frontend abre el selector de cuentas de Google y recibe un access
     * token; aquí se le pregunta a Google de quién es y si fue emitido para
     * esta aplicación. Google no crea cuentas: solo entra quien ya tiene una,
     * dada de alta por el administrador con ese mismo correo, porque el rol lo
     * decide la clínica y no quien llega.
     */
    public function google(GoogleLoginRequest $request): JsonResponse
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            return response()->json([
                'success' => false,
                'message' => 'El inicio de sesión con Google no está disponible.',
            ], 503);
        }

        try {
            // tokeninfo rechaza los tokens falsos o vencidos; lo que queda por
            // revisar es para quién se emitió y si el correo es confiable.
            $respuesta = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'access_token' => $request->access_token,
            ]);
        } catch (ConnectionException) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo contactar a Google. Intente de nuevo en un momento.',
            ], 503);
        }

        $datos = $respuesta->json() ?? [];

        // Un token emitido para otra aplicación también es auténtico; si no se
        // revisara "aud", cualquier sitio con botón de Google serviría de llave.
        if (! $respuesta->ok()
            || ($datos['aud'] ?? null) !== $clientId
            || ! filter_var($datos['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || empty($datos['sub'])
            || empty($datos['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo verificar la cuenta de Google.',
            ], 401);
        }

        // Primero por el identificador de Google, que no cambia; si la cuenta
        // nunca ha entrado con Google, por el correo.
        $user = User::where('google_id', $datos['sub'])->first()
            ?? User::where('email', $datos['email'])->first();

        if (! $user || ($user->google_id && $user->google_id !== $datos['sub'])) {
            Bitacora::registrar(
                'login_fallido',
                $user
                    ? 'Intento con Google de '.$datos['email'].', vinculado a otra cuenta de Google.'
                    : 'Intento con Google de un correo no registrado: '.$datos['email'].'.',
                $user,
                autor: $user,
                correo: $datos['email'],
            );

            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta de Google no tiene acceso al sistema. Comuníquese con el administrador.',
            ], 401);
        }

        if (! $user->activo) {
            Bitacora::registrar(
                'login_fallido',
                'Intento con Google de una cuenta desactivada: '.$user->email.'.',
                $user,
                autor: $user,
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario se encuentra inactivo. Comuníquese con el administrador.',
            ], 403);
        }

        // La primera vez que la cuenta entra con Google queda vinculada y, si
        // no tiene foto, se le pone la de Google. Solo esa vez: después la
        // foto es del sistema y quien la cambie o la quite no la ve volver.
        if (! $user->google_id) {
            $user->forceFill(['google_id' => $datos['sub']])->save();

            if (! $user->foto_path) {
                $this->traerFotoDeGoogle($user, $request->access_token);
            }
        }

        return $this->emitirSesion($user, $user->name.' inició sesión con Google.');
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

                Bitacora::registrar(
                    'contrasena_restablecida',
                    $user->name.' restableció su contraseña con el enlace del correo.',
                    $user,
                    autor: $user,
                );

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
            // Cuánto duró la sesión: del token creado al iniciarla hasta ahora.
            $duracion = $token instanceof PersonalAccessToken && $token->created_at
                ? (int) $token->created_at->diffInSeconds(now())
                : null;

            Bitacora::registrar(
                'logout',
                $request->user()->name.' cerró sesión'
                    .($duracion !== null ? ' tras '.Bitacora::duracion($duracion) : '').'.',
                $request->user(),
                $duracion !== null ? ['duracion_segundos' => $duracion] : [],
            );

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
        $expiracion = VencimientoDeSesion::paraToken($user->currentAccessToken());

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'activo' => $user->activo,
                'telefono' => $user->telefono,
                'foto_url' => $user->foto_url,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'expires_in' => $expiracion['expires_in'],
                'expires_at' => $expiracion['expires_at'],
            ],
        ], 200);
    }

    /**
     * Abrir la sesión de un usuario ya autenticado, venga de la contraseña o
     * de Google: el frontend recibe la misma respuesta por los dos caminos.
     */
    private function emitirSesion(User $user, string $descripcion): JsonResponse
    {
        // Las sesiones que este usuario dejó morir por inactividad ya no
        // autentican, pero su fila sigue ocupando espacio. Se limpian al entrar
        // porque no hay tareas programadas en el proyecto y este es el único
        // momento en que se sabe, sin buscarlo, que hay algo que barrer.
        $this->olvidarSesionesVencidas($user);

        // Crear token API con Sanctum. La sesión dura mientras se use: el plazo
        // que fija config/sanctum.php ('inactividad') se cuenta desde la última
        // petición, no desde este momento. Se le informa al cliente para que
        // muestre la cuenta atrás; las respuestas siguientes la corrigen con las
        // cabeceras X-Session-Expires-*.
        $tokenNuevo = $user->createToken('auth_token');
        $expiracion = VencimientoDeSesion::paraToken($tokenNuevo->accessToken);

        Bitacora::registrar('login', $descripcion, $user, autor: $user);

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
                    'foto_url' => $user->foto_url,
                ],
            ],
        ], 200);
    }

    /**
     * Copiar la foto de la cuenta de Google al disco, como si el usuario la
     * hubiera subido. Se copia y no se enlaza para que cambiarla en el
     * sistema no dependa de Google ni lo afecte, y para que siga viéndose
     * aunque cambie la de Google.
     *
     * Si algo falla, el usuario entra igual y se queda con sus iniciales: la
     * foto no vale un inicio de sesión fallido.
     */
    private function traerFotoDeGoogle(User $user, string $accessToken): void
    {
        try {
            $perfil = Http::timeout(10)
                ->withToken($accessToken)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');

            $url = $perfil->ok() ? $perfil->json('picture') : null;

            if (! is_string($url) || ! str_starts_with($url, 'https://')) {
                return;
            }

            // Google entrega la foto en miniatura (=s96-c); se pide más grande
            // para que no se vea borrosa en el perfil.
            $url = preg_replace('/=s\d+(-c)?$/', '=s256-c', $url);

            $imagen = Http::timeout(10)->get($url);

            $extension = match (strtok((string) $imagen->header('Content-Type'), ';')) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => null,
            };

            if (! $imagen->ok()
                || ! $extension
                || strlen($imagen->body()) > StoreFotoRequest::MAXIMO_KB * 1024) {
                return;
            }

            $ruta = self::CARPETA_FOTOS.'/'.Str::random(40).'.'.$extension;
            Storage::disk('public')->put($ruta, $imagen->body());

            $user->update(['foto_path' => $ruta]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Borrar los tokens de este usuario que ya no autentican por inactividad.
     */
    private function olvidarSesionesVencidas(User $user): void
    {
        $minutos = VencimientoDeSesion::minutos();

        if ($minutos <= 0) {
            return;
        }

        $limite = now()->subMinutes($minutos);

        // Un token sin uso se juzga por su creación, igual que al autenticar.
        $user->tokens()
            ->whereRaw('COALESCE(last_used_at, created_at) <= ?', [$limite])
            ->delete();
    }
}
