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
        // Tabla pivote que resuelve todas las listas de selección múltiple de la
        // historia clínica (síntomas, enfermedades, CEAP, indicaciones, etc.).
        Schema::create('clinical_history_option', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinical_history_id')
                ->constrained('clinical_histories')
                ->onDelete('cascade');

            $table->foreignId('clinical_option_id')
                ->constrained('clinical_options')
                ->onDelete('cascade');

            // Una opción no puede marcarse dos veces en la misma historia
            $table->unique(['clinical_history_id', 'clinical_option_id'], 'clinical_history_option_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_history_option');
    }
};
