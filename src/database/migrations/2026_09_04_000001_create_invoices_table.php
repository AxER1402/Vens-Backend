<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos de cobro de la clínica.
     *
     * Un mismo documento sirve para las dos salidas que se piden en el
     * mostrador: el recibo interno, que se entrega en el momento, y la factura
     * electrónica, que además hay que certificar ante la SAT. La diferencia no
     * es de estructura sino de destino, así que van en la misma tabla y las
     * columnas del régimen FEL quedan nulas mientras no haya certificador.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->onDelete('restrict');

            // La consulta que se está cobrando. Es opcional porque también se
            // cobran cosas que no nacen de una consulta —un estudio suelto, un
            // material— pero cuando existe, es lo que ata el dinero al
            // expediente.
            $table->foreignId('clinical_history_id')
                ->nullable()
                ->constrained('clinical_histories')
                ->onDelete('set null');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // 'recibo' se emite y ya; 'factura' además se manda a certificar
            $table->string('tipo', 20)->default('recibo');

            // Correlativo propio de la clínica, independiente del que asigna
            // la SAT al certificar
            $table->string('serie', 10)->default('A');
            $table->unsignedInteger('numero');

            $table->date('fecha_emision');

            // Datos del receptor tal como van impresos. Se copian y no se leen
            // del paciente al imprimir: si mañana cambia su NIT, el documento
            // ya emitido tiene que seguir diciendo lo que decía.
            $table->string('nit_receptor', 20)->default('CF');
            $table->string('nombre_receptor', 150);
            $table->string('direccion_receptor', 255)->nullable();

            $table->string('moneda', 3)->default('GTQ');

            // En Guatemala el IVA va incluido en el precio, así que el total es
            // lo que paga el paciente y la base y el impuesto se desglosan a
            // partir de él. Se guarda el porcentaje con el que se calculó para
            // que un documento viejo no cambie si mañana cambia la tasa.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('iva_porcentaje', 5, 2)->default(12);
            $table->decimal('iva_monto', 12, 2)->default(0);

            $table->string('metodo_pago', 40)->default('Efectivo');
            $table->string('estado', 20)->default('Emitida');
            $table->string('motivo_anulacion', 255)->nullable();
            $table->text('observaciones')->nullable();

            // ── Régimen FEL (Factura Electrónica en Línea) ──────────────────
            // Todo nulo hasta que haya API del certificador. Están desde ahora
            // para que conectar la certificación no obligue a migrar datos ya
            // emitidos: el documento nace 'No aplica' o 'Pendiente' y lo único
            // que hace falta después es llenar estas columnas.
            $table->string('fel_estado', 20)->default('No aplica');
            $table->string('fel_uuid', 60)->nullable();
            $table->string('fel_serie', 20)->nullable();
            $table->string('fel_numero', 30)->nullable();
            $table->timestamp('fel_certificado_at')->nullable();
            $table->string('fel_certificador', 100)->nullable();
            $table->text('fel_mensaje')->nullable();

            $table->timestamps();

            $table->unique(['serie', 'numero']);
            $table->index('fecha_emision');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
