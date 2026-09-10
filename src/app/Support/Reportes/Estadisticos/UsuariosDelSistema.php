<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\Appointment;
use App\Models\ClinicalHistory;
use App\Models\DopplerReport;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Las cuentas con las que se entra al sistema.
 *
 * A diferencia del resto del catálogo, aquí el período no recorta el listado:
 * un censo de cuentas que solo enseñara las creadas entre dos fechas no serviría
 * para lo que se pide —saber quién tiene acceso hoy y con qué rol—, que es
 * justamente la pregunta de una auditoría. El rango sí manda en las dos
 * secciones donde significa algo: las altas del período y la actividad que cada
 * cuenta dejó dentro de él.
 *
 * Se listan también las cuentas desactivadas. Una cuenta inactiva sigue estando
 * en la base con su correo y su rol, y omitirla haría que el reporte dijera que
 * no existe.
 */
class UsuariosDelSistema extends ReportePeriodo
{
    /** @var Collection<int, User>|null */
    private ?Collection $cuentas = null;

    /** @var array<string, string>|null */
    private ?array $nombresDeRol = null;

    private int $sobran = 0;

    public function titulo(): string
    {
        return 'Reporte de Usuarios';
    }

    public function archivo(): string
    {
        return 'usuarios';
    }

    public function subtitulo(): string
    {
        // El censo es de hoy y no del rango: decirlo en el subtítulo evita que
        // se lea el total de cuentas como «las que había en septiembre».
        return 'Cuentas del sistema · actividad del período '.$this->periodo->etiqueta();
    }

    public function secciones(): array
    {
        if ($this->cuentas()->isEmpty()) {
            return $this->sinDatos('No hay cuentas registradas en el sistema.');
        }

        return array_values(array_filter([
            $this->resumen(),
            $this->porRol(),
            $this->directorio(),
            $this->avisoRecorte($this->sobran),
            $this->altasDelPeriodo(),
            $this->actividad(),
        ]));
    }

    protected function metaExtra(): array
    {
        $cuentas = $this->cuentas();

        return [
            'Cuentas' => (string) $cuentas->count(),
            'Activas' => (string) $cuentas->where('activo', true)->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function resumen(): array
    {
        $cuentas = $this->cuentas();
        $activas = $cuentas->where('activo', true)->count();
        $altas = $this->altas()->count();

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen de las cuentas',
            'campos' => [
                'Cuentas registradas' => (string) $cuentas->count(),
                'Cuentas activas' => $activas.' ('.$this->porcentaje($activas, $cuentas->count()).')',
                'Cuentas desactivadas' => (string) ($cuentas->count() - $activas),
                'Roles en uso' => (string) $cuentas->pluck('rol')->filter()->unique()->count(),
                'Administradores activos' => (string) $cuentas->where('activo', true)->where('rol', 'administrador')->count(),
                'Altas en el período' => (string) $altas,
                'Cuentas sin teléfono' => (string) $cuentas->filter(fn (User $u) => ! Formato::hayDato($u->telefono))->count(),
                'Cuenta más antigua' => Formato::fecha($cuentas->min('created_at')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function porRol(): ?array
    {
        $conteo = [];

        foreach ($this->cuentas()->groupBy('rol') as $rol => $delRol) {
            $conteo[$this->nombreDeRol((string) $rol)] = $delRol->count();
        }

        return $this->tablaFrecuencia(
            'Cuentas por rol',
            'Rol',
            $this->ordenarPorFrecuencia($conteo),
            $this->cuentas()->count(),
            'Cuentas',
        );
    }

    /**
     * Censo de cuentas. Es el cuerpo del reporte: nombre, cómo se entra, con qué
     * rol y desde cuándo.
     *
     * @return array<string, mixed>
     */
    private function directorio(): array
    {
        $filas = $this->cuentas()
            ->map(fn (User $usuario) => [
                Formato::valor($usuario->name),
                Formato::valor($usuario->email),
                Formato::valor($usuario->telefono),
                $this->nombreDeRol((string) $usuario->rol),
                $usuario->activo ? 'Activa' : 'Desactivada',
                Formato::fecha($usuario->created_at),
            ])
            ->values()
            ->all();

        [$filas, $this->sobran] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Cuentas registradas',
            'encabezados' => ['Usuario', 'Correo', 'Teléfono', 'Rol', 'Estado', 'Alta'],
            'filas' => $filas,
            'anchos' => [22, 26, 14, 15, 12, 11],
        ];
    }

    /**
     * Cuentas creadas dentro del rango.
     *
     * Se omite la sección si no hubo ninguna, en vez de imprimir una tabla
     * vacía: el dato «no se dieron altas» ya lo dice el resumen.
     *
     * @return array<string, mixed>|null
     */
    private function altasDelPeriodo(): ?array
    {
        $altas = $this->altas();

        if ($altas->isEmpty()) {
            return null;
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Altas del período',
            'encabezados' => ['Fecha', 'Usuario', 'Correo', 'Rol', 'Estado'],
            'filas' => $altas
                ->sortBy('created_at')
                ->map(fn (User $usuario) => [
                    Formato::fecha($usuario->created_at),
                    Formato::valor($usuario->name),
                    Formato::valor($usuario->email),
                    $this->nombreDeRol((string) $usuario->rol),
                    $usuario->activo ? 'Activa' : 'Desactivada',
                ])
                ->values()
                ->all(),
            'anchos' => [13, 24, 29, 18, 16],
        ];
    }

    /**
     * Lo que cada cuenta dejó registrado en el período.
     *
     * Es la mitad útil de un reporte de usuarios: dice qué cuentas se están
     * usando de verdad. Una cuenta activa con todo a cero durante meses es la
     * que conviene revisar.
     *
     * Se cuentan por `created_by` —quien levantó el registro— salvo las citas,
     * que se cuentan por `medico_id`, que es a quien se le asignaron.
     *
     * @return array<string, mixed>|null
     */
    private function actividad(): ?array
    {
        $citas = $this->conteoPorUsuario(
            Appointment::query()
                ->whereBetween('fecha_hora_inicio', $this->periodo->limitesHora())
                ->pluck('medico_id')
        );

        $consultas = $this->conteoPorUsuario(
            ClinicalHistory::query()
                ->where('activo', true)
                ->whereBetween('fecha_consulta', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()])
                ->pluck('created_by')
        );

        $estudios = $this->conteoPorUsuario(
            DopplerReport::query()
                ->where('activo', true)
                ->whereBetween('fecha_estudio', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()])
                ->pluck('created_by')
        );

        $documentos = $this->conteoPorUsuario(
            Invoice::query()
                ->vigentes()
                ->byDateRange($this->periodo->fechaInicio(), $this->periodo->fechaFin())
                ->pluck('created_by')
        );

        if ($citas === [] && $consultas === [] && $estudios === [] && $documentos === []) {
            return null;
        }

        $filas = $this->cuentas()
            ->map(function (User $usuario) use ($citas, $consultas, $estudios, $documentos) {
                $suyas = [
                    $citas[$usuario->id] ?? 0,
                    $consultas[$usuario->id] ?? 0,
                    $estudios[$usuario->id] ?? 0,
                    $documentos[$usuario->id] ?? 0,
                ];

                return [
                    'orden' => [-array_sum($suyas), mb_strtolower((string) $usuario->name)],
                    'fila' => [
                        Formato::valor($usuario->name),
                        $this->nombreDeRol((string) $usuario->rol),
                        ...array_map(fn (int $n) => (string) $n, $suyas),
                        (string) array_sum($suyas),
                    ],
                ];
            })
            ->sortBy('orden')
            ->pluck('fila')
            ->values()
            ->all();

        return [
            'tipo' => 'tabla',
            'titulo' => 'Actividad registrada en el período',
            'encabezados' => ['Usuario', 'Rol', 'Citas', 'Consultas', 'Estudios', 'Cobros', 'Total'],
            'filas' => $filas,
            'anchos' => [25, 16, 11, 14, 12, 11, 11],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, User>
     */
    private function cuentas(): Collection
    {
        if ($this->cuentas !== null) {
            return $this->cuentas;
        }

        return $this->cuentas = User::query()
            ->orderBy('rol')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'telefono', 'rol', 'activo', 'created_at']);
    }

    /**
     * @return Collection<int, User>
     */
    private function altas(): Collection
    {
        [$desde, $hasta] = $this->periodo->limitesHora();

        return $this->cuentas()->filter(
            fn (User $usuario) => $usuario->created_at !== null
                && $usuario->created_at->between($desde, $hasta)
        );
    }

    /**
     * Cuántos registros levantó cada usuario, a partir de la columna de autoría.
     *
     * @param  Collection<int, int|null>  $autores
     * @return array<int, int>
     */
    private function conteoPorUsuario(Collection $autores): array
    {
        return $autores
            ->filter()
            ->countBy()
            ->all();
    }

    /**
     * Nombre presentable de un rol.
     *
     * Se toma del catálogo de roles para que el reporte y la pantalla de
     * usuarios llamen igual a lo mismo; si el rol no está en el catálogo se
     * imprime tal cual viene, que es más honesto que dejarlo en blanco.
     */
    private function nombreDeRol(string $slug): string
    {
        if ($this->nombresDeRol === null) {
            $this->nombresDeRol = Role::query()->pluck('nombre', 'slug')->all();
        }

        return $this->nombresDeRol[$slug] ?? ($slug === '' ? Formato::VACIO : ucfirst($slug));
    }
}
