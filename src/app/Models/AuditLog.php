<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una entrada de la bitácora de auditoría. Ver App\Support\Auditoria\Bitacora.
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    /** La bitácora no se corrige: no hay updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'usuario_nombre',
        'usuario_correo',
        'usuario_rol',
        'evento',
        'categoria',
        'descripcion',
        'sujeto_tipo',
        'sujeto_id',
        'cambios',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
