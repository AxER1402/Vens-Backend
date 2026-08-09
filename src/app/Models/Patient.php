<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';

    protected $fillable = [
        'nombre',
        'edad',
        'telefono',
        'lugar_residencia',
        'estado_civil',
        'religion',
        'estado',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'edad' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Citas agendadas al paciente.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    /**
     * Historias clínicas registradas al paciente.
     */
    public function clinicalHistories(): HasMany
    {
        return $this->hasMany(ClinicalHistory::class, 'patient_id');
    }
}
