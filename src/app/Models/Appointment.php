<?php

namespace App\Models;

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
     * Filtrar citas por un rango de fechas.
     */
    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('fecha_hora_inicio', [$from, $to]);
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
