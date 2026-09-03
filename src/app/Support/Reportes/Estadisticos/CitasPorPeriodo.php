<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\Appointment;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Actividad de la agenda en el período: qué se agendó, qué se cumplió y qué se
 * perdió.
 *
 * Las citas se cuentan por `fecha_hora_inicio`, no por cuándo se registraron:
 * lo que interesa de «las citas de septiembre» es lo que ocupó la agenda de
 * septiembre, aunque se hubieran agendado en julio.
 */
class CitasPorPeriodo extends ReportePeriodo
{
    /**
     * Orden en que se presentan los estados. No es alfabético ni por cantidad:
     * es el recorrido de una cita, para que la tabla se lea como el ciclo de
     * vida que describe.
     *
     * @var array<int, string>
     */
    private const ESTADOS = ['Programada', 'Confirmada', 'Reagendada', 'Completada', 'Cancelada', 'No Asistió'];

    /** @var Collection<int, Appointment>|null */
    private ?Collection $citas = null;

    private int $sobran = 0;

    public function titulo(): string
    {
        return 'Citas por Período';
    }

    public function archivo(): string
    {
        return 'citas';
    }

    public function secciones(): array
    {
        $citas = $this->citas();

        if ($citas->isEmpty()) {
            return $this->sinDatos('No hay citas agendadas entre las fechas indicadas.');
        }

        return array_values(array_filter([
            $this->resumen($citas),
            $this->porEstado($citas),
            $this->porMedico($citas),
            $this->detalle($citas),
            $this->avisoRecorte($this->sobran),
        ]));
    }

    protected function metaExtra(): array
    {
        $meta = ['Citas' => (string) $this->citas()->count()];

        if ($medico = $this->medicoFiltrado()) {
            $meta['Médico'] = $medico;
        }

        return $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Collection<int, Appointment>  $citas
     * @return array<string, mixed>
     */
    private function resumen(Collection $citas): array
    {
        $total = $citas->count();
        $atendidas = $citas->where('estado', 'Completada')->count();
        $canceladas = $citas->where('estado', 'Cancelada')->count();
        $ausencias = $citas->where('estado', 'No Asistió')->count();

        // Se cuentan como «pendientes» las que siguen vivas en la agenda. En un
        // período ya pasado, una cifra alta aquí no es carga futura: son citas
        // que nadie cerró, y por eso se informa aparte y no dentro de atendidas.
        $pendientes = $citas->whereIn('estado', ['Programada', 'Confirmada', 'Reagendada'])->count();

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen del período',
            'campos' => [
                'Citas agendadas' => (string) $total,
                'Pacientes distintos' => (string) $citas->pluck('patient_id')->unique()->count(),
                'Atendidas (Completada)' => $atendidas.' ('.$this->porcentaje($atendidas, $total).')',
                'Sin cerrar' => $pendientes.' ('.$this->porcentaje($pendientes, $total).')',
                'Canceladas' => $canceladas.' ('.$this->porcentaje($canceladas, $total).')',
                'Inasistencias' => $ausencias.' ('.$this->porcentaje($ausencias, $total).')',
                'Promedio diario' => number_format($total / max(1, $this->periodo->dias()), 1, '.', ''),
                'Días con agenda' => (string) $citas
                    ->map(fn (Appointment $cita) => $cita->fecha_hora_inicio?->toDateString())
                    ->filter()
                    ->unique()
                    ->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $citas
     * @return array<string, mixed>|null
     */
    private function porEstado(Collection $citas): ?array
    {
        $conteo = $citas->countBy('estado')->all();

        // Los estados conocidos van en el orden del ciclo de vida; cualquier
        // otro que aparezca en la base se añade al final en vez de perderse.
        $ordenado = [];

        foreach (self::ESTADOS as $estado) {
            if (! empty($conteo[$estado])) {
                $ordenado[$estado] = $conteo[$estado];
            }
        }

        foreach ($conteo as $estado => $cantidad) {
            $ordenado[$estado] ??= $cantidad;
        }

        return $this->tablaFrecuencia('Distribución por estado', 'Estado', $ordenado, $citas->count(), 'Citas');
    }

    /**
     * @param  Collection<int, Appointment>  $citas
     * @return array<string, mixed>|null
     */
    private function porMedico(Collection $citas): ?array
    {
        $filas = $citas
            ->groupBy(fn (Appointment $cita) => $cita->medico?->name ?? 'Sin médico asignado')
            ->map(fn (Collection $grupo, string $medico) => [
                'orden' => [-$grupo->count(), mb_strtolower($medico)],
                'fila' => [
                    $medico,
                    (string) $grupo->count(),
                    (string) $grupo->where('estado', 'Completada')->count(),
                    (string) $grupo->where('estado', 'Cancelada')->count(),
                    (string) $grupo->where('estado', 'No Asistió')->count(),
                    $this->porcentaje($grupo->where('estado', 'Completada')->count(), $grupo->count()),
                ],
            ])
            ->sortBy('orden')
            ->pluck('fila')
            ->values()
            ->all();

        if ($filas === []) {
            return null;
        }

        return [
            'tipo' => 'tabla',
            'titulo' => 'Citas por médico',
            'encabezados' => ['Médico', 'Agendadas', 'Atendidas', 'Canceladas', 'Inasistencias', '% atención'],
            'filas' => $filas,
            'anchos' => [32, 13, 13, 14, 15, 13],
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $citas
     * @return array<string, mixed>
     */
    private function detalle(Collection $citas): array
    {
        $filas = $citas->map(fn (Appointment $cita) => [
            Formato::fechaHora($cita->fecha_hora_inicio),
            Formato::valor($cita->patient?->nombre),
            Formato::valor($cita->medico?->name),
            $this->resumir($cita->motivo, 46),
            Formato::valor($cita->estado),
            $this->resumir($cita->motivo_cancelacion, 40),
        ])->values()->all();

        [$filas, $this->sobran] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Detalle de citas',
            'encabezados' => ['Fecha y hora', 'Paciente', 'Médico', 'Motivo', 'Estado', 'Motivo de cancelación'],
            'filas' => $filas,
            'anchos' => [14, 20, 16, 21, 11, 18],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, Appointment>
     */
    private function citas(): Collection
    {
        if ($this->citas !== null) {
            return $this->citas;
        }

        $consulta = Appointment::query()
            ->with(['patient', 'medico'])
            ->whereBetween('fecha_hora_inicio', $this->periodo->limitesHora());

        if (! empty($this->filtros['medico_id'])) {
            $consulta->where('medico_id', $this->filtros['medico_id']);
        }

        if (! empty($this->filtros['patient_id'])) {
            $consulta->where('patient_id', $this->filtros['patient_id']);
        }

        return $this->citas = $consulta->orderBy('fecha_hora_inicio')->get();
    }

    private function medicoFiltrado(): ?string
    {
        if (empty($this->filtros['medico_id'])) {
            return null;
        }

        return $this->citas()->first()?->medico?->name;
    }
}
