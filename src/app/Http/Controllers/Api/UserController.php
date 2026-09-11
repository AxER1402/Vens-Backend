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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
     * El personal al que se le puede asignar una cita o un informe.
     *
     * Existe aparte del listado de usuarios porque no es lo mismo: aquel
     * administra las cuentas y solo lo abre el administrador, mientras que
     * este llena un selector que necesita también quien agenda desde el
     * mostrador. Por eso devuelve el nombre y nada más: ni el correo, ni el
     * teléfono, ni si la cuenta está activa; para elegir un médico en una
     * lista no hace falta saber nada de eso.
     *
     * Solo el rol `medico`. Antes entraba también el administrador, y el
     * selector de «Médico tratante» ofrecía elegir a quien lleva las cuentas
     * del sistema para atender una cita. Quien administra y quien atiende son
     * cosas distintas aunque a veces coincidan en la misma persona: si la
     * doctora además administra, su cuenta se crea con rol `medico` y otra
     * aparte para administrar, que es lo que deja la agenda legible.
     */
    public function medicos(): JsonResponse
    {
        $medicos = User::select('id', 'name', 'rol')
            ->where('rol', 'medico')
            ->where('activo', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $medicos,
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

    /**
     * Borrar una cuenta del sistema, sin vuelta atrás.
     *
     * Desactivar es lo correcto para quien trabajó aquí: la cuenta deja de
     * entrar, pero su nombre sigue al pie de lo que firmó. Lo que no resolvía
     * es la cuenta que nunca llegó a usarse —el correo mal escrito, la que se
     * creó dos veces, la del turno que no empezó—, que se quedaba en la lista
     * para siempre ocupando además su correo, que es único.
     *
     * Hay tres cosas que no se pueden borrar, y no por precaución sino porque
     * el sistema quedaría mal:
     *
     * 1. Una cuenta con registros a su nombre. Las nueve claves foráneas hacia
     *    users son ON DELETE SET NULL, así que la base no se queja: lo que pasa
     *    es peor, que la consulta se queda sin quién la levantó y la factura
     *    sin quién la emitió. La firma de los informes clínicos sale de ahí
     *    (ver Ficha::firma), de modo que borrar a un médico dejaría sin firmar
     *    todo lo que firmó. Para eso está desactivar.
     * 2. La propia cuenta. Quien borra se quedaría sin sesión a mitad de la
     *    operación.
     * 3. El último administrador activo. Sin él nadie puede volver a entrar a
     *    la gestión de usuarios, ni siquiera para deshacerlo.
     */
    public function forceDestroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puede eliminar su propia cuenta. Pídaselo a otro administrador.',
            ], 422);
        }

        $registros = $this->registrosDe($user);

        if (array_sum($registros) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar a '.$user->name.': tiene '
                    .$this->enumerar($registros).' a su nombre, y borrar la cuenta los dejaría '
                    .'sin quién los registró. Desactívelo: deja de entrar al sistema y su nombre '
                    .'sigue en lo que firmó.',
                'data' => ['registros' => $registros],
            ], 409);
        }

        if ($user->rol === 'administrador' && $user->activo) {
            $otrosAdmins = User::where('rol', 'administrador')
                ->where('activo', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($otrosAdmins === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Es el único administrador activo. Sin él nadie podría volver a '
                        .'entrar a la gestión de usuarios: nombre a otro administrador antes.',
                ], 422);
            }
        }

        // La foto de perfil vive en el disco, no en la fila: borrar solo la
        // fila dejaría el archivo suelto para siempre.
        if ($user->foto_path) {
            Storage::disk('public')->delete($user->foto_path);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado del sistema.',
        ], 200);
    }

    /**
     * Qué hay registrado a nombre de este usuario, por tipo.
     *
     * Se cuentan las dos columnas de autoría donde existen —quién lo levantó y
     * quién lo corrigió—: cualquiera de las dos hace que la cuenta esté
     * sujetando un registro.
     *
     * @return array<string, int>
     */
    private function registrosDe(User $user): array
    {
        return [
            'consultas' => DB::table('clinical_histories')
                ->where('created_by', $user->id)->orWhere('updated_by', $user->id)->count(),
            'estudios' => DB::table('doppler_reports')
                ->where('created_by', $user->id)->orWhere('updated_by', $user->id)->count(),
            'documentos de cobro' => DB::table('invoices')
                ->where('created_by', $user->id)->count(),
            'citas' => DB::table('appointments')
                ->where('medico_id', $user->id)->orWhere('created_by', $user->id)->count(),
            'días bloqueados' => DB::table('blocked_days')
                ->where('created_by', $user->id)->count(),
        ];
    }

    /**
     * «4 consultas, 3 documentos de cobro y 1 cita».
     *
     * @param  array<string, int>  $registros
     */
    private function enumerar(array $registros): string
    {
        $partes = [];

        foreach ($registros as $nombre => $cuantos) {
            if ($cuantos > 0) {
                $partes[] = $cuantos.' '.$nombre;
            }
        }

        if (count($partes) === 1) {
            return $partes[0];
        }

        $ultima = array_pop($partes);

        return implode(', ', $partes).' y '.$ultima;
    }
}
