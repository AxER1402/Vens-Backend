<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clinical_options', function (Blueprint $table) {
            $table->id();

            // Lista a la que pertenece la opción (ej: sintomas, ceap_diagnostico, indicaciones)
            $table->string('categoria', 50);

            // Valor clínico tal como se registra y se muestra (ej: 'Calambres')
            $table->string('valor', 150);

            // Etiqueta alternativa para la interfaz cuando difiere del valor almacenado
            $table->string('etiqueta', 150)->nullable();

            // Orden de presentación dentro de su categoría
            $table->unsignedSmallInteger('orden')->default(0);

            // Permite retirar una opción del catálogo sin borrar las historias que ya la usan
            $table->boolean('activo')->default(true);

            $table->timestamps();

            // Una misma opción no puede repetirse dentro de su categoría
            $table->unique(['categoria', 'valor']);
            $table->index(['categoria', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_options');
    }
};
