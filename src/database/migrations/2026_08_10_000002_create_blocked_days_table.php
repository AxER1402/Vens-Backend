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
        Schema::create('blocked_days', function (Blueprint $table) {
            $table->id();

            // Usuario (Administrador/Médico) que registró el bloqueo
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Rango de fechas bloqueado. Un día suelto se guarda con inicio = fin
            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            // Detalle del bloqueo
            $table->string('motivo', 255);
            $table->string('tipo', 50)->default('Feriado');

            $table->timestamps();

            // Índice para resolver rápido "¿qué está bloqueado en esta semana?"
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_days');
    }
};
