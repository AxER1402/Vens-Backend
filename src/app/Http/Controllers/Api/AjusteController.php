<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajuste\StoreLogoRequest;
use App\Http\Requests\Ajuste\UpdateAgendaRequest;
use App\Http\Requests\Ajuste\UpdateAjustesRequest;
use App\Support\Agenda\Horario;
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
     * Guardar el horario de atención y la duración de una cita.
     *
     * Aparte de update() porque el horario no es un texto más: son siete días
     * con su propia validación, y quien lo cambia no es solo el
     * administrador —la agenda también es cosa del médico, igual que los
     * feriados y las vacaciones.
     */
    public function updateAgenda(UpdateAgendaRequest $request): JsonResponse
    {
        $valores = [];

        if ($request->has('horario')) {
            $valores['agenda.horario'] = $this->horarioLimpio($request->input('horario', []));
        }

        if ($request->has('duracion_cita')) {
            $valores['agenda.duracion_cita'] = (int) $request->input('duracion_cita');
        }

        Ajustes::guardar($valores);

        return response()->json([
            'success' => true,
            'message' => 'El horario de atención se actualizó.',
            'data' => $this->cuerpo(),
        ], 200);
    }

    /**
     * Deja el horario con los siete días y solo con lo que se entiende.
     *
     * Un día inactivo pierde sus horas: guardarlas invitaría a que la próxima
     * pantalla las mostrara y diera a entender que se atiende un día cerrado.
     *
     * Y si no queda ningún día abierto se guarda vacío, que es como se dice
     * «sin horario» y devuelve la agenda a aceptar cualquier hora. Los siete
     * días cerrados serían lo contrario —la agenda entera bloqueada, sin
     * manera de agendar nada—, y nadie que desmarca las siete casillas está
     * pidiendo eso: está quitando la restricción. Para cerrar de verdad unos
     * días están los bloqueos, que llevan motivo y fecha.
     *
     * @param  array<string, mixed>  $entrante
     * @return array<string, array{activo: bool, abre: ?string, cierra: ?string}>
     */
    private function horarioLimpio(array $entrante): array
    {
        $limpio = [];
        $algunoAbierto = false;

        foreach (Horario::DIAS as $dia) {
            $tramo = is_array($entrante[$dia] ?? null) ? $entrante[$dia] : [];
            $activo = (bool) ($tramo['activo'] ?? false);
            $algunoAbierto = $algunoAbierto || $activo;

            $limpio[$dia] = [
                'activo' => $activo,
                'abre' => $activo ? ($tramo['abre'] ?? null) : null,
                'cierra' => $activo ? ($tramo['cierra'] ?? null) : null,
            ];
        }

        return $algunoAbierto ? $limpio : [];
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
     * La dirección con la que el navegador ve un archivo de public/.
     *
     * @param  string  $relativa  Ruta relativa a public/ ('img/isotipo.png').
     */
    private function urlPublica(string $relativa): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($relativa, '/');
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

            // La dirección se arma con APP_URL y no con asset(), que la deduce
            // de la petición: Nginx pasa a PHP la cabecera Host por su variable
            // $host, que descarta el puerto, así que el logo salía en
            // http://localhost/img/… —el puerto 80, donde no atiende nadie— y
            // la pantalla lo pintaba roto. Es la misma dirección con la que el
            // disco 'public' arma la de la foto de perfil, que sí se ve.
            'logo_url' => $logo ? $this->urlPublica($logo) : null,

            // El horario se devuelve ya con los siete días aunque nunca se
            // haya guardado: así la pantalla pinta la tabla sin tener que
            // inventarse los días que faltan, y la agenda sabe que un horario
            // vacío significa «sin restricción».
            'horario' => Horario::configurado(),
            'duracion_cita' => Horario::duracionCita(),
        ];
    }
}
