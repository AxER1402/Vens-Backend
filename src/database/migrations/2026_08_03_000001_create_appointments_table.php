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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            
            // Relación con el paciente
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->onDelete('cascade');

            // Médico o especialista asignado a la cita
            $table->foreignId('medico_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Usuario (Recepcionista/Admin/Médico) que agendó la cita
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Fechas y horas de inicio y fin para control preciso por año/mes/día/hora
            $table->dateTime('fecha_hora_inicio');
            $table->dateTime('fecha_hora_fin');

            // Detalle de la cita
            $table->string('motivo', 255);
            $table->string('estado', 50)->default('Programada');

            // Razón si la cita es cancelada
            $table->text('motivo_cancelacion')->nullable();

            // Notas u observaciones adicionales
            $table->text('notas')->nullable();

            $table->timestamps();

            // Índices para consultas eficientes por calendario, fechas y estados
            $table->index(['fecha_hora_inicio', 'fecha_hora_fin']);
            $table->index('patient_id');
            $table->index('medico_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
