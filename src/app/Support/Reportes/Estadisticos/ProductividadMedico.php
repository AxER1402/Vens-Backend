<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\Appointment;
use App\Models\ClinicalHistory;
use App\Models\DopplerReport;
use App\Models\User;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Producción del personal clínico en el período.
 *
 * Se mide por tres vías que no son intercambiables: las citas que la agenda le
 * asignó, las consultas que dejó escritas en el expediente y los estudios que
 * levantó. Un profesional puede tener muchas citas y pocas consultas —eso es
 * expediente sin cerrar, no falta de trabajo— y el reporte tiene que dejar ver
 * esa diferencia en vez de esconderla en un único número de «atenciones».
 *
 * La autoría de consultas y estudios se toma de `created_by`, que es quien
 * levantó el registro. Es el único dato de autoría que la base guarda.
 */
class ProductividadMedico extends ReportePeriodo
{
    /** Días de la semana en el orden en que abre la clínica. */
    private const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /** @var Collection<int, Appointment>|null */
    private ?Collection $citas = null;

    /** @var Collection<int, ClinicalHistory>|null */
    private ?Collection $consultas = null;

    /** @var Collection<int, DopplerReport>|null */
    private ?Collection $estudios = null;

    public function titulo(): string
    {
        return 'Productividad por Médico';
    }

    public function archivo(): string
    {
        return 'productividad-medico';
    }

    public function secciones(): array
    {
        $tabla = $this->porProfesional();

        if ($tabla === null) {
            return $this->sinDatos('No hay citas, consultas ni estudios registrados entre las fechas indicadas.');
        }

        return array_values(array_filter([
            $this->resumen(),
            $tabla,
            $this->porDiaDeSemana(),
        ]));
    }

    protected function metaExtra(): array
    {
        return [
            'Citas' => (string) $this->citas()->count(),
            'Consultas' => (string) $this->consultas()->count(),
            'Estudios' => (string) $this->estudios()->count(),
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
        $citas = $this->citas();
        $atendidas = $citas->where('estado', 'Completada')->count();
        $dias = max(1, $this->periodo->dias());

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen del período',
            'campos' => [
                'Profesionales con actividad' => (string) $this->profesionales()->count(),
                'Citas asignadas' => (string) $citas->count(),
                'Citas atendidas' => $atendidas.' ('.$this->porcentaje($atendidas, max(1, $citas->count())).')',
                'Consultas registradas' => (string) $this->consultas()->count(),
                'Estudios de Ecodöppler' => (string) $this->estudios()->count(),
                'Consultas en borrador' => (string) $this->consultas()->where('estado_registro', 'Borrador')->count(),
                'Consultas por día' => number_format($this->consultas()->count() / $dias, 1, '.', ''),
                'Citas atendidas por día' => number_format($atendidas / $dias, 1, '.', ''),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function porProfesional(): ?array
    {
        $profesionales = $this->profesionales();

        if ($profesionales->isEmpty()) {
            return null;
        }

        $citasPorMedico = $this->citas()->groupBy('medico_id');
        $consultasPorAutor = $this->consultas()->groupBy('created_by');
        $estudiosPorAutor = $this->estudios()->groupBy('created_by');

        $filas = $profesionales
            ->map(function (array $profesional) use ($citasPorMedico, $consultasPorAutor, $estudiosPorAutor) {
                $id = $profesional['id'];

                $citas = $citasPorMedico->get($id, collect());
                $atendidas = $citas->where('estado', 'Completada')->count();
                $consultas = $consultasPorAutor->get($id, collect())->count();
                $estudios = $estudiosPorAutor->get($id, collect())->count();

                return [
                    'orden' => [-($atendidas + $consultas + $estudios), mb_strtolower($profesional['nombre'])],
                    'fila' => [
                        $profesional['nombre'],
                        $profesional['rol'],
                        (string) $citas->count(),
                        (string) $atendidas,
                        (string) $consultas,
                        (string) $estudios,
                        $citas->count() > 0 ? $this->porcentaje($atendidas, $citas->count()) : Formato::VACIO,
                    ],
                ];
            })
            ->sortBy('orden')
            ->pluck('fila')
            ->values()
            ->all();

        return [
            'tipo' => 'tabla',
            'titulo' => 'Producción por profesional',
            'encabezados' => ['Profesional', 'Rol', 'Citas', 'Atendidas', 'Consultas', 'Estudios', '% atención'],
            'filas' => $filas,
            'anchos' => [26, 15, 10, 12, 13, 11, 13],
        ];
    }

    /**
     * Reparto de la carga por día de la semana.
     *
     * Es el dato con el que se decide si abrir otro día o mover un turno: dice
     * dónde se acumula el trabajo, cosa que el total del mes esconde.
     *
     * @return array<string, mixed>|null
     */
    private function porDiaDeSemana(): ?array
    {
        $citas = $this->citas();

        if ($citas->isEmpty()) {
            return null;
        }

        $conteo = [];

        foreach (self::DIAS as $numero => $nombre) {
            $delDia = $citas->filter(
                fn (Appointment $cita) => $cita->fecha_hora_inicio?->dayOfWeekIso === $numero
            );

            if ($delDia->isNotEmpty()) {
                $conteo[$nombre] = $delDia->count();
            }
        }

        return $this->tablaFrecuencia('Carga de la agenda por día de la semana', 'Día', $conteo, $citas->count(), 'Citas');
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    /**
     * Personal a listar: el clínico de la casa más cualquiera que haya dejado
     * actividad en el período.
     *
     * Se incluye a quien ya no está activo si trabajó dentro del rango: borrarlo
     * del reporte cambiaría a posteriori la producción de un mes ya cerrado.
     *
     * @return Collection<int, array{id: int, nombre: string, rol: string}>
     */
    private function profesionales(): Collection
    {
        $conActividad = collect()
            ->merge($this->citas()->pluck('medico_id'))
            ->merge($this->consultas()->pluck('created_by'))
            ->merge($this->estudios()->pluck('created_by'))
            ->filter()
            ->unique();

        $consulta = User::query()->where(function ($query) use ($conActividad) {
            $query->where(fn ($q) => $q->where('rol', 'medico')->where('activo', true));

            if ($conActividad->isNotEmpty()) {
                $query->orWhereIn('id', $conActividad->all());
            }
        });

        if (! empty($this->filtros['medico_id'])) {
            $consulta->where('id', $this->filtros['medico_id']);
        }

        return $consulta->orderBy('name')->get()
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'nombre' => Formato::valor($usuario->name),
                'rol' => ucfirst((string) $usuario->rol),
            ]);
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function citas(): Collection
    {
        if ($this->citas !== null) {
            return $this->citas;
        }

        $consulta = Appointment::query()
            ->whereBetween('fecha_hora_inicio', $this->periodo->limitesHora());

        if (! empty($this->filtros['medico_id'])) {
            $consulta->where('medico_id', $this->filtros['medico_id']);
        }

        return $this->citas = $consulta->get();
    }

    /**
     * @return Collection<int, ClinicalHistory>
     */
    private function consultas(): Collection
    {
        if ($this->consultas !== null) {
            return $this->consultas;
        }

        $consulta = ClinicalHistory::query()
            ->where('activo', true)
            ->whereBetween('fecha_consulta', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()]);

        if (! empty($this->filtros['medico_id'])) {
            $consulta->where('created_by', $this->filtros['medico_id']);
        }

        return $this->consultas = $consulta->get();
    }

    /**
     * @return Collection<int, DopplerReport>
     */
    private function estudios(): Collection
    {
        if ($this->estudios !== null) {
            return $this->estudios;
        }

        $consulta = DopplerReport::query()
            ->where('activo', true)
            ->whereBetween('fecha_estudio', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()]);

        if (! empty($this->filtros['medico_id'])) {
            $consulta->where('created_by', $this->filtros['medico_id']);
        }

        return $this->estudios = $consulta->get();
    }
}
