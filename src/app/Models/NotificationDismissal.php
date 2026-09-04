<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca de que un usuario descartó a mano un aviso del campanario.
 */
class NotificationDismissal extends Model
{
    protected $table = 'notification_dismissals';

    protected $fillable = [
        'user_id',
        'clave',
        'descartada_at',
    ];

    protected function casts(): array
    {
        return [
            'descartada_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
