<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El recordatorio de WhatsApp se intenta cada minuto mientras la cita esté
     * a unas 24 horas; estas dos columnas son lo que evita mandarlo dos veces.
     * Se guarda también el resultado porque no todo intento termina en un
     * mensaje: un paciente sin WhatsApp se da por atendido para no insistir,
     * y eso tiene que quedar dicho.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('recordatorio_at')
                  ->nullable()
                  ->after('notas');
            $table->string('recordatorio_resultado', 20)
                  ->nullable()
                  ->after('recordatorio_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['recordatorio_at', 'recordatorio_resultado']);
        });
    }
};
