<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de servicios y tarifas.
     *
     * Los renglones de un recibo se escribían a mano cada vez: la consulta, el
     * eco-Doppler, la sesión de escleroterapia, con su precio tecleado de
     * memoria. Eso hace que el mismo servicio aparezca escrito de tres formas
     * distintas —y a tres precios— según quién cobró.
     *
     * El catálogo solo rellena el formulario. Los renglones ya emitidos siguen
     * guardando su propia descripción y su propio precio, así que subir una
     * tarifa no reescribe ningún recibo anterior.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->decimal('precio', 12, 2)->default(0);

            // Baja lógica: un servicio que se deja de prestar desaparece del
            // selector, pero los recibos que lo cobraron siguen explicándose.
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
