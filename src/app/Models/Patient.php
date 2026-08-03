<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
