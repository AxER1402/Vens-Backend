<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renglones de un documento de cobro.
     *
     * Una consulta rara vez se cobra sola: lleva el estudio, la sesión de
     * escleroterapia, las medias. Cada cosa es un renglón con su cantidad y su
     * precio, que es además la forma en que la SAT espera el detalle.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->onDelete('cascade');

            // 'B' bien, 'S' servicio: es la clasificación que pide el DTE
            $table->string('tipo', 1)->default('S');

            $table->string('descripcion', 255);
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
