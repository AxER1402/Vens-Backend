<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un servicio del catálogo, con su tarifa.
 */
#[Fillable(['nombre', 'descripcion', 'precio', 'activo'])]
class Service extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }
}
