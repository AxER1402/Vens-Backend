<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un ajuste guardado.
 *
 * Casi nadie lo usa directamente: lo normal es pasar por App\Support\Ajustes,
 * que es quien sabe qué claves existen, cuáles sobrescriben la configuración
 * y cuándo hay que olvidar la caché.
 */
#[Fillable(['clave', 'valor'])]
class Setting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'json',
        ];
    }
}
