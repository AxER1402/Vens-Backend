<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $fillable = [
        'patient_id',
        'medico_id',
        'created_by',
        'fecha_hora_inicio',
        'fecha_hora_fin',
        'motivo',
        'estado',
        'motivo_cancelacion',
        'notas',
    ];

    /**
     * La hora de una cita es hora de pared, no un instante.
     *
     * «Las cinco de la tarde» es lo que se acordó con el paciente y lo que
     * está escrito en la base; no lleva zona horaria porque no la necesita.
     * Sin el formato explícito, Laravel serializa el cast `datetime` como
     * instante UTC —«23:00Z» para las cinco de la tarde en Guatemala— y la
     * agenda, que lee la hora tal como viene, pintaba la cita seis horas más
     * tarde. Se serializa como se guardó.
     */
    protected function casts(): array
    {
        return [
            'fecha_hora_inicio' => 'datetime:Y-m-d H:i:s',
            'fecha_hora_fin' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /**
     * Relación con el Paciente asociado a la cita.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Relación con el Médico o especialista asignado.
     */
    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    /**
     * Relación con el usuario que agendó la cita.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes de Filtrado (Control Multianual, Mensual, Diario)
    |--------------------------------------------------------------------------
    */

    /**
     * Filtrar citas por año específico (ej: 2026).
     */
    public function scopeByYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('fecha_hora_inicio', $year);
    }

    /**
     * Filtrar citas por año y mes específico.
     */
    public function scopeByMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('fecha_hora_inicio', $year)
                     ->whereMonth('fecha_hora_inicio', $month);
    }

    /**
     * Filtrar citas por un día específico (YYYY-MM-DD).
     */
    public function scopeByDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('fecha_hora_inicio', $date);
    }

    /**
     * Filtrar citas por un rango de fechas, con los dos extremos dentro.
     *
     * `fecha_hora_inicio` es un datetime, así que comparar contra la fecha
     * pelada lee el último día como su medianoche y deja fuera esa jornada
     * entera: la vista semanal pedía de lunes a domingo y se quedaba sin el
     * domingo. El extremo de arriba se estira hasta el final del día.
     *
     * Se comparan marcas de tiempo y no DATE(fecha_hora_inicio) para que el
     * índice siga sirviendo. Envolver la columna en una función obliga a MySQL
     * a recorrer la tabla entera, y esta crece con cada cita: en una consulta
     * con ochenta pacientes al día son veinte mil filas al año, y la agenda se
     * repinta cada vez que alguien cambia de semana.
     *
     * Es lo mismo que hace Periodo::limitesHora() para los reportes.
     */
    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        $desde = self::comoFecha($from)?->startOfDay();
        $hasta = self::comoFecha($to)?->endOfDay();

        // Una fecha que no se entiende no filtra nada, como antes de que este
        // scope supiera de horas. Es preferible una lista vacía a un error 500
        // por un parámetro mal escrito en la dirección.
        if ($desde === null || $hasta === null) {
            return $query;
        }

        return $query
            ->where('fecha_hora_inicio', '>=', $desde)
            ->where('fecha_hora_inicio', '<=', $hasta);
    }

    /** La fecha, o null si el texto no es una. */
    private static function comoFecha(string $texto): ?Carbon
    {
        try {
            return Carbon::parse($texto);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Filtrar por paciente.
     */
    public function scopeByPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Filtrar por médico asignado.
     */
    public function scopeByMedico(Builder $query, int $medicoId): Builder
    {
        return $query->where('medico_id', $medicoId);
    }

    /**
     * Filtrar por estado de la cita.
     */
    public function scopeByEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }
}
