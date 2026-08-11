<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedDay extends Model
{
    use HasFactory;

    protected $table = 'blocked_days';

    protected $fillable = [
        'created_by',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
        ];
    }

    /**
     * Tipos de bloqueo admitidos por la agenda.
     */
    public const TIPOS = ['Feriado', 'Vacaciones', 'Cierre', 'Otro'];

    /**
     * Relación con el usuario que registró el bloqueo.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes de Filtrado
    |--------------------------------------------------------------------------
    */

    /**
     * Bloqueos que se solapan con el rango consultado (agenda semanal o mensual).
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('fecha_inicio', '<=', $to)
                     ->where('fecha_fin', '>=', $from);
    }

    /**
     * Bloqueos que cubren un día específico (YYYY-MM-DD).
     */
    public function scopeCoveringDate(Builder $query, string $date): Builder
    {
        return $query->where('fecha_inicio', '<=', $date)
                     ->where('fecha_fin', '>=', $date);
    }

    /**
     * Bloqueos que tocan un año determinado.
     */
    public function scopeByYear(Builder $query, int $year): Builder
    {
        return $query->overlapping("{$year}-01-01", "{$year}-12-31");
    }
}
