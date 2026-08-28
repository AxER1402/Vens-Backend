<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ClinicalHistory extends Model
{
    use HasFactory;

    /**
     * Listas de selección múltiple que maneja la historia clínica.
     * Cada una corresponde a una categoría del catálogo clinical_options.
     */
    public const CATEGORIAS = [
        'zonas_pierna',
        'sintomas',
        'sintomas_aumentan',
        'sintomas_disminuyen',
        'enfermedades',
        'ceap_diagnostico',
        'tx_zonas',
        'indicaciones',
        'observaciones',
    ];

    protected $table = 'clinical_histories';

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'fecha_consulta',
        'consulta_por',
        'disminuyen_otros',
        'familiar_varices',
        'alergias',
        'cirugias',
        'gestas',
        'abortos',
        'partos',
        'cesareas',
        'hijos_vivos',
        'hijos_muertos',
        'ultima_menstruacion',
        'hormonas',
        'enfermedades_otros',
        'presion_arterial',
        'frecuencia_cardiaca',
        'frecuencia_respiratoria',
        'temperatura',
        'peso',
        'perimetro_tobillo',
        'perimetro_pantorrilla',
        'ubicacion_patologia',
        'ceap_c',
        'esclero_concentracion',
        'esclero_forma',
        'esclero_volumen',
        'indicaciones_detalle',
        'indicaciones_otros',
        'evolucion',
        'estado_general',
        'notas',
        'mapeo_venoso_path',
        'mapeo_venoso_datos',
        'mapeo_venoso_updated_at',
        'estado_registro',
        'activo',
        'created_by',
        'updated_by',
    ];

    /**
     * Atributos calculados que se agregan a la respuesta JSON.
     */
    protected $appends = [
        'selecciones',
        'mapeo_venoso_url',
    ];

    protected function casts(): array
    {
        return [
            'fecha_consulta' => 'date:Y-m-d',
            'ultima_menstruacion' => 'date:Y-m-d',
            // Medicamento recetado en cada indicación: { indicación: detalle }
            'indicaciones_detalle' => 'array',
            // Documento vectorial del mapeo: { version, plantilla, objetos }
            'mapeo_venoso_datos' => 'array',
            'mapeo_venoso_updated_at' => 'datetime',
            'familiar_varices' => 'boolean',
            'activo' => 'boolean',
            'gestas' => 'integer',
            'abortos' => 'integer',
            'partos' => 'integer',
            'cesareas' => 'integer',
            'hijos_vivos' => 'integer',
            'hijos_muertos' => 'integer',
            'frecuencia_cardiaca' => 'integer',
            'frecuencia_respiratoria' => 'integer',
            'temperatura' => 'decimal:1',
            'peso' => 'decimal:2',
            'perimetro_tobillo' => 'decimal:1',
            'perimetro_pantorrilla' => 'decimal:1',
            'esclero_concentracion' => 'decimal:2',
            'esclero_volumen' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Paciente al que pertenece la historia clínica.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Cita en la que se levantó la historia, si se registró desde la agenda.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Usuario que registró la historia clínica.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Último usuario que modificó la historia clínica.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Estudios de Ecodöppler venoso adjuntos a la consulta.
     */
    public function dopplerReports(): HasMany
    {
        return $this->hasMany(DopplerReport::class, 'clinical_history_id');
    }

    /**
     * Opciones marcadas en las listas de selección múltiple.
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(
            ClinicalOption::class,
            'clinical_history_option',
            'clinical_history_id',
            'clinical_option_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Atributos calculados
    |--------------------------------------------------------------------------
    */

    /**
     * Devolver las selecciones agrupadas por categoría, siempre con las nueve
     * listas presentes, para que el formulario pueda inicializarse directamente.
     *
     * @return array<string, array<int, string>>
     */
    public function getSeleccionesAttribute(): array
    {
        $marcadas = $this->options->groupBy('categoria');

        $selecciones = [];
        foreach (self::CATEGORIAS as $categoria) {
            $selecciones[$categoria] = $marcadas->has($categoria)
                ? $marcadas->get($categoria)->pluck('valor')->values()->all()
                : [];
        }

        return $selecciones;
    }

    /**
     * URL pública del mapeo venoso almacenado.
     */
    public function getMapeoVenosoUrlAttribute(): ?string
    {
        if (! $this->mapeo_venoso_path) {
            return null;
        }

        return Storage::disk('public')->url($this->mapeo_venoso_path);
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
     * Filtrar por estado del registro (Borrador o Finalizada).
     */
    public function scopeByEstadoRegistro(Builder $query, string $estadoRegistro): Builder
    {
        return $query->where('estado_registro', $estadoRegistro);
    }

    /**
     * Filtrar por rango de fechas de consulta.
     */
    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('fecha_consulta', [$from, $to]);
    }
}
