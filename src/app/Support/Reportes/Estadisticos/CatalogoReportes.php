<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\User;
use App\Support\Reportes\Emision;
use Illuminate\Http\Request;

/**
 * Los reportes de período que el sistema sabe emitir.
 *
 * Cada uno se declara **una sola vez**: su clave pública, quién puede emitirlo,
 * qué filtros admite y qué clase lo construye. El controlador no conoce ningún
 * reporte por su nombre y el frontend pinta su catálogo con lo que devuelve
 * `descriptores()`, así que añadir el reporte número once es escribir su clase
 * y una entrada aquí.
 *
 * Los informes de un expediente concreto —historia clínica, mapeo venoso y
 * Ecodöppler— no viven en este catálogo: se piden por el id de su registro y
 * tienen sus propias rutas.
 */
final class CatalogoReportes
{
    /**
     * @var array<string, array{clase: class-string<ReportePeriodo>, titulo: string, descripcion: string, roles: array<int, string>, filtros: array<int, string>}>
     */
    private const REPORTES = [
        'pacientes-atendidos' => [
            'clase' => PacientesAtendidos::class,
            'titulo' => 'Pacientes atendidos',
            'descripcion' => 'Pacientes con consultas en el período, con cuántas veces vinieron, sus estudios y quiénes son de primera vez.',
            'roles' => ['administrador', 'medico', 'recepcionista'],
            'filtros' => ['patient_id'],
        ],
        'citas' => [
            'clase' => CitasPorPeriodo::class,
            'titulo' => 'Citas por período',
            'descripcion' => 'Actividad de la agenda: agendadas, atendidas, canceladas e inasistencias, por estado y por médico.',
            'roles' => ['administrador', 'medico', 'recepcionista'],
            'filtros' => ['medico_id', 'patient_id'],
        ],
        'productividad-medico' => [
            'clase' => ProductividadMedico::class,
            'titulo' => 'Productividad por médico',
            'descripcion' => 'Citas asignadas y atendidas, consultas registradas y estudios levantados por cada profesional.',
            'roles' => ['administrador', 'medico'],
            'filtros' => ['medico_id'],
        ],
        'diagnosticos-ceap' => [
            'clase' => DiagnosticosCeap::class,
            'titulo' => 'Diagnósticos CEAP',
            'descripcion' => 'Distribución de la clase clínica C0–C6 y de los ejes etiológico, anatómico y fisiopatológico.',
            'roles' => ['administrador', 'medico'],
            'filtros' => ['patient_id'],
        ],
        'sintomas-antecedentes' => [
            'clase' => SintomasFrecuentes::class,
            'titulo' => 'Síntomas y antecedentes frecuentes',
            'descripcion' => 'Qué refieren los pacientes, qué agrava o alivia sus síntomas y qué antecedentes patológicos traen.',
            'roles' => ['administrador', 'medico'],
            'filtros' => ['patient_id'],
        ],
        'tratamientos-indicaciones' => [
            'clase' => TratamientosIndicaciones::class,
            'titulo' => 'Tratamientos e indicaciones',
            'descripcion' => 'Sesiones de escleroterapia con sus medidas, zonas tratadas e indicaciones prescritas.',
            'roles' => ['administrador', 'medico'],
            'filtros' => ['patient_id'],
        ],
        'evolucion-seguimiento' => [
            'clase' => EvolucionSeguimiento::class,
            'titulo' => 'Evolución y seguimiento',
            'descripcion' => 'Respuesta al tratamiento, estado al cierre de cada consulta y lista nominal de los casos que exigen seguimiento.',
            'roles' => ['administrador', 'medico'],
            'filtros' => ['patient_id'],
        ],
        'estudios-ecodoppler' => [
            'clase' => EstudiosEcodoppler::class,
            'titulo' => 'Estudios de Ecodöppler',
            'descripcion' => 'Consolidado de los estudios del período, con los segmentos que aparecen con reflujo y sus medidas medias.',
            'roles' => ['administrador', 'medico'],
            'filtros' => ['patient_id'],
        ],
    ];

    /** Filtros que se aceptan de la petición, más allá del rango de fechas. */
    private const FILTROS = ['patient_id', 'medico_id'];

    /** ¿Existe un reporte con esta clave? */
    public static function existe(string $clave): bool
    {
        return array_key_exists($clave, self::REPORTES);
    }

    /**
     * Roles autorizados a emitir un reporte.
     *
     * @return array<int, string>
     */
    public static function roles(string $clave): array
    {
        return self::REPORTES[$clave]['roles'] ?? [];
    }

    /**
     * Construir el reporte pedido con el período y los filtros de la petición.
     */
    public static function construir(string $clave, Request $request, ?User $emisor): ReportePeriodo
    {
        $definicion = self::REPORTES[$clave];

        /** @var class-string<ReportePeriodo> $clase */
        $clase = $definicion['clase'];

        return new $clase(
            Periodo::desdePeticion($request),
            self::filtros($request, $definicion['filtros']),
            $emisor,
        );
    }

    /**
     * Catálogo para el frontend, ya recortado a lo que este usuario puede pedir.
     *
     * Se envía la lista en lugar de que la interfaz la lleve escrita: si un
     * reporte se retira o cambia de permisos, la pantalla se entera sola.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function descriptores(?User $usuario): array
    {
        $rol = $usuario?->rol;

        $descriptores = [];

        foreach (self::REPORTES as $clave => $definicion) {
            if (! in_array($rol, $definicion['roles'], true)) {
                continue;
            }

            $descriptores[] = [
                'clave' => $clave,
                'titulo' => $definicion['titulo'],
                'descripcion' => $definicion['descripcion'],
                'filtros' => $definicion['filtros'],
                'formatos' => array_keys(Emision::TIPOS),
            ];
        }

        return $descriptores;
    }

    /**
     * Filtros presentes en la petición y admitidos por el reporte.
     *
     * Un filtro que el reporte no declara se ignora en vez de aplicarse a
     * ciegas: `medico_id` en un reporte de síntomas no significa nada, y
     * dejarlo pasar produciría un documento con un encabezado que promete un
     * recorte que las tablas no tienen.
     *
     * @param  array<int, string>  $admitidos
     * @return array<string, int>
     */
    private static function filtros(Request $request, array $admitidos): array
    {
        $filtros = [];

        foreach (self::FILTROS as $filtro) {
            if (! in_array($filtro, $admitidos, true)) {
                continue;
            }

            $valor = $request->query($filtro);

            if (is_numeric($valor) && (int) $valor > 0) {
                $filtros[$filtro] = (int) $valor;
            }
        }

        return $filtros;
    }
}
