<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte de Ecodöppler venoso de miembros inferiores.
 *
 * Un estudio informa siempre los dos miembros con los mismos hallazgos, por eso
 * cada campo existe dos veces con el prefijo del lado: der_ (MID) e izq_ (MII).
 */
class DopplerReport extends Model
{
    use HasFactory;

    /**
     * Lados que informa un estudio, en el orden en que se presentan.
     *
     * @var array<int, string>
     */
    public const LADOS = ['der', 'izq'];

    /**
     * Segmentos del sistema venoso superficial cuyo nombre es siempre el mismo.
     * Ocupan las tres primeras posiciones de la lista de cada lado.
     *
     * @var array<int, string>
     */
    public const SEGMENTOS_FIJOS = ['SFJ', 'GSV Muslo', 'GSV Pierna'];

    /**
     * Segmentos que informa cada lado: los tres fijos más dos que el médico
     * nombra según lo que haya evaluado en el estudio.
     */
    public const TOTAL_SEGMENTOS = 5;

    /**
     * Medidas numéricas de cada segmento, en el orden en que se informan. La
     * duración es la del reflujo, en segundos.
     *
     * No llevan tope superior a propósito: cualquier techo que se ponga acaba
     * rechazando el hallazgo excepcional en vez del error de digitación, y el
     * estudio se informa con lo que midió el equipo.
     *
     * @var array<int, string>
     */
    public const MEDIDAS_SEGMENTO = ['diametro_max', 'velocidad', 'duracion', 'diametro'];

    protected $table = 'doppler_reports';

    protected $fillable = [
        'patient_id',
        'clinical_history_id',
        'fecha_estudio',

        // ── Miembro inferior derecho (MID) ────────────────────────────────
        'der_profundo',
        'der_segmentos',
        'der_perforantes',
        'der_trombosis',

        // ── Miembro inferior izquierdo (MII) ──────────────────────────────
        'izq_profundo',
        'izq_segmentos',
        'izq_perforantes',
        'izq_trombosis',

        'conclusion',
        'estado_registro',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_estudio' => 'date:Y-m-d',
            'activo' => 'boolean',
            'der_segmentos' => 'array',
            'izq_segmentos' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Paciente al que pertenece el estudio.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Consulta del expediente a la que se adjunta el estudio.
     */
    public function clinicalHistory(): BelongsTo
    {
        return $this->belongsTo(ClinicalHistory::class, 'clinical_history_id');
    }

    /**
     * Usuario que registró el estudio.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Último usuario que modificó el estudio.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes de Filtrado
    |--------------------------------------------------------------------------
    */

    /**
     * Filtrar por paciente.
     */
    public function scopeByPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Filtrar por la consulta del expediente a la que pertenece el estudio.
     */
    public function scopeByClinicalHistory(Builder $query, int $clinicalHistoryId): Builder
    {
        return $query->where('clinical_history_id', $clinicalHistoryId);
    }

    /**
     * Filtrar por estado del registro (Borrador o Finalizada).
     */
    public function scopeByEstadoRegistro(Builder $query, string $estadoRegistro): Builder
    {
        return $query->where('estado_registro', $estadoRegistro);
    }

    /**
     * Filtrar por rango de fechas del estudio.
     */
    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('fecha_estudio', [$from, $to]);
    }
}
