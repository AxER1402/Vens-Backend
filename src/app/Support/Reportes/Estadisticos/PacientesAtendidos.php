<?php

namespace App\Support\Reportes\Estadisticos;

use App\Models\ClinicalHistory;
use App\Models\DopplerReport;
use App\Support\Reportes\Formato;
use Illuminate\Support\Collection;

/**
 * Pacientes con actividad clínica en el período.
 *
 * «Atendido» se define por la consulta registrada, no por la cita agendada: una
 * cita puede quedar en «Programada» para siempre, mientras que una historia
 * clínica con fecha dentro del rango es prueba de que el paciente estuvo. Por
 * eso este reporte cuenta expedientes y el de citas cuenta agenda; son dos
 * preguntas distintas y mezclarlas daba totales que no cuadraban entre sí.
 */
class PacientesAtendidos extends ReportePeriodo
{
    /** @var Collection<int, ClinicalHistory>|null */
    private ?Collection $consultas = null;

    public function titulo(): string
    {
        return 'Pacientes Atendidos';
    }

    public function archivo(): string
    {
        return 'pacientes-atendidos';
    }

    public function secciones(): array
    {
        $consultas = $this->consultas();

        if ($consultas->isEmpty()) {
            return $this->sinDatos('No se registraron consultas entre las fechas indicadas.');
        }

        $porPaciente = $consultas->groupBy('patient_id');
        $estudios = $this->estudiosPorPaciente($porPaciente->keys()->all());

        return array_values(array_filter([
            $this->resumen($consultas, $porPaciente, $estudios),
            $this->detalle($porPaciente, $estudios),
            $this->avisoRecorte($this->sobran),
        ]));
    }

    protected function metaExtra(): array
    {
        return [
            'Pacientes' => (string) $this->consultas()->pluck('patient_id')->unique()->count(),
            'Consultas' => (string) $this->consultas()->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones
    |--------------------------------------------------------------------------
    */

    private int $sobran = 0;

    /**
     * @param  Collection<int, ClinicalHistory>  $consultas
     * @param  Collection<int, Collection<int, ClinicalHistory>>  $porPaciente
     * @param  array<int, int>  $estudios
     * @return array<string, mixed>
     */
    private function resumen(Collection $consultas, Collection $porPaciente, array $estudios): array
    {
        $pacientes = $porPaciente->count();
        $nuevos = $this->pacientesNuevos($porPaciente->keys()->all());

        return [
            'tipo' => 'campos',
            'titulo' => 'Resumen del período',
            'campos' => [
                'Pacientes atendidos' => (string) $pacientes,
                'Consultas registradas' => (string) $consultas->count(),
                'Pacientes nuevos' => $nuevos.' ('.$this->porcentaje($nuevos, $pacientes).')',
                'Pacientes en seguimiento' => (string) ($pacientes - $nuevos),
                'Consultas por paciente' => $this->media($consultas->count(), $pacientes),
                'Estudios de estos pacientes' => (string) array_sum($estudios),
                'Consultas finalizadas' => (string) $consultas->where('estado_registro', 'Finalizada')->count(),
                'Consultas en borrador' => (string) $consultas->where('estado_registro', 'Borrador')->count(),
            ],
        ];
    }

    /**
     * Una fila por paciente, del más atendido al menos atendido.
     *
     * @param  Collection<int, Collection<int, ClinicalHistory>>  $porPaciente
     * @param  array<int, int>  $estudios
     * @return array<string, mixed>
     */
    private function detalle(Collection $porPaciente, array $estudios): array
    {
        $filas = $porPaciente
            ->map(function (Collection $consultas, int $patientId) use ($estudios) {
                $paciente = $consultas->first()->patient;
                $ultima = $consultas->max('fecha_consulta');

                return [
                    'orden' => [-$consultas->count(), mb_strtolower((string) $paciente?->nombre)],
                    'fila' => [
                        Formato::valor($paciente?->nombre),
                        Formato::hayDato($paciente?->edad) ? Formato::entero($paciente->edad) : Formato::VACIO,
                        Formato::valor($paciente?->lugar_residencia),
                        (string) $consultas->count(),
                        (string) ($estudios[$patientId] ?? 0),
                        Formato::fecha($ultima),
                        Formato::valor($paciente?->estado),
                    ],
                ];
            })
            ->sortBy('orden')
            ->pluck('fila')
            ->values()
            ->all();

        [$filas, $this->sobran] = $this->recortar($filas);

        return [
            'tipo' => 'tabla',
            'titulo' => 'Detalle por paciente',
            'encabezados' => ['Paciente', 'Edad', 'Residencia', 'Consultas', 'Estudios', 'Última consulta', 'Estado'],
            'filas' => $filas,
            'anchos' => [26, 7, 20, 11, 10, 14, 12],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Datos
    |--------------------------------------------------------------------------
    */

    /**
     * Consultas del período, con su paciente.
     *
     * @return Collection<int, ClinicalHistory>
     */
    private function consultas(): Collection
    {
        if ($this->consultas !== null) {
            return $this->consultas;
        }

        $consulta = ClinicalHistory::query()
            ->with('patient')
            ->where('activo', true)
            ->whereBetween('fecha_consulta', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()]);

        if (! empty($this->filtros['patient_id'])) {
            $consulta->where('patient_id', $this->filtros['patient_id']);
        }

        return $this->consultas = $consulta->orderBy('fecha_consulta')->get();
    }

    /**
     * Estudios de Ecodöppler del período, contados por paciente.
     *
     * Se limita a los pacientes que aparecen en el reporte. El período puede
     * tener estudios de alguien que no vino a consulta —el estudio se levanta
     * antes de abrir el expediente—, y contarlos en el resumen dejaba un total
     * que no cuadraba con la suma de la columna: quien lee el documento
     * concluye que la tabla está mal, no que mide otra cosa.
     *
     * @param  array<int, int>  $pacientes
     * @return array<int, int>
     */
    private function estudiosPorPaciente(array $pacientes): array
    {
        if ($pacientes === []) {
            return [];
        }

        return DopplerReport::query()
            ->where('activo', true)
            ->whereIn('patient_id', $pacientes)
            ->whereBetween('fecha_estudio', [$this->periodo->fechaInicio(), $this->periodo->fechaFin()])
            ->get(['patient_id'])
            ->countBy('patient_id')
            ->all();
    }

    /**
     * Cuántos de estos pacientes vinieron por primera vez dentro del período.
     *
     * Se mira su primera consulta **de toda la historia**, no la primera del
     * rango: si no, cualquier paciente antiguo que volviera en septiembre
     * contaría como paciente nuevo de septiembre.
     *
     * @param  array<int, int>  $pacientes
     */
    private function pacientesNuevos(array $pacientes): int
    {
        if ($pacientes === []) {
            return 0;
        }

        return ClinicalHistory::query()
            ->where('activo', true)
            ->whereIn('patient_id', $pacientes)
            ->selectRaw('patient_id, MIN(fecha_consulta) as primera')
            ->groupBy('patient_id')
            ->get()
            ->filter(fn ($fila) => $fila->primera >= $this->periodo->fechaInicio())
            ->count();
    }
}
