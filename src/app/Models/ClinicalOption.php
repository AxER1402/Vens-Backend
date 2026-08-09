<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalOption extends Model
{
    use HasFactory;

    protected $table = 'clinical_options';

    protected $fillable = [
        'categoria',
        'valor',
        'etiqueta',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Filtrar el catálogo por una categoría específica (ej: sintomas).
     */
    public function scopeByCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }

    /**
     * Restringir a las opciones vigentes del catálogo.
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
