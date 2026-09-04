<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Documento de cobro: recibo interno o factura electrónica.
 */
class Invoice extends Model
{
    protected $table = 'invoices';

    public const TIPO_RECIBO = 'recibo';

    public const TIPO_FACTURA = 'factura';

    /** NIT genérico del consumidor final, cuando el paciente no da el suyo. */
    public const NIT_CONSUMIDOR_FINAL = 'CF';

    protected $fillable = [
        'patient_id',
        'clinical_history_id',
        'created_by',
        'tipo',
        'serie',
        'numero',
        'fecha_emision',
        'nit_receptor',
        'nombre_receptor',
        'direccion_receptor',
        'moneda',
        'subtotal',
        'descuento',
        'total',
        'iva_porcentaje',
        'iva_monto',
        'metodo_pago',
        'estado',
        'motivo_anulacion',
        'observaciones',
        'fel_estado',
        'fel_uuid',
        'fel_serie',
        'fel_numero',
        'fel_certificado_at',
        'fel_certificador',
        'fel_mensaje',
    ];

    protected function casts(): array
    {
        return [
            // Con formato explícito, como el resto de fechas del sistema: son
            // fechas de calendario y viajan como tales, sin zona horaria.
            'fecha_emision' => 'date:Y-m-d',
            'fel_certificado_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'iva_porcentaje' => 'decimal:2',
            'iva_monto' => 'decimal:2',
        ];
    }

    /**
     * Los renglones salen siempre en el orden en que se escribieron: sin
     * ordenarlos, la base los devuelve como quiere y el documento impreso lista
     * los conceptos en un orden distinto al que se tecleó en el mostrador.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id')->orderBy('id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function clinicalHistory(): BelongsTo
    {
        return $this->belongsTo(ClinicalHistory::class, 'clinical_history_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * El número que se le muestra a la gente: «A-14».
     */
    public function getCorrelativoAttribute(): string
    {
        return "{$this->serie}-{$this->numero}";
    }

    /**
     * Documentos que sí entraron dinero: los anulados no cuentan para nada.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', 'Emitida');
    }

    public function scopeByDateRange(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('fecha_emision', [$desde, $hasta]);
    }

    public function scopeByPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Siguiente correlativo de una serie.
     *
     * Se calcula sobre el máximo guardado y no sobre la cantidad de filas: un
     * documento anulado sigue ocupando su número, que es justamente lo que
     * exige un correlativo.
     */
    public static function siguienteNumero(string $serie): int
    {
        return (int) static::where('serie', $serie)->max('numero') + 1;
    }
}
