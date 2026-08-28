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
        Schema::create('doppler_reports', function (Blueprint $table) {
            $table->id();

            // Paciente dueño del estudio
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->onDelete('cascade');

            // Consulta del expediente a la que se adjunta el estudio (opcional:
            // el reporte puede levantarse antes de finalizar la historia clínica)
            $table->foreignId('clinical_history_id')
                ->nullable()
                ->constrained('clinical_histories')
                ->onDelete('set null');

            $table->date('fecha_estudio');

            // Un mismo estudio informa los dos miembros inferiores con los mismos
            // hallazgos, por eso cada columna se repite con el prefijo del lado:
            // der_ = miembro inferior derecho (MID), izq_ = izquierdo (MII).
            foreach (['der', 'izq'] as $lado) {
                // Eje venoso profundo: hallazgo descriptivo
                $table->text("{$lado}_profundo")->nullable();

                // Sistema venoso superficial. Se guarda como lista ordenada y no
                // como columnas sueltas porque los tres primeros segmentos son
                // fijos (SFJ, GSV Muslo, GSV Pierna) y los dos últimos los nombra
                // el médico según lo que haya evaluado. Cada posición lleva:
                // { nombre, diametro_max, velocidad, duracion, observaciones, diametro }
                $table->json("{$lado}_segmentos")->nullable();

                // Perforantes y signos de trombosis
                $table->string("{$lado}_perforantes", 255)->nullable();
                $table->string("{$lado}_trombosis", 255)->nullable();
            }

            // Interpretación del estudio que se adjunta a la consulta
            $table->text('conclusion')->nullable();

            // ── Control y auditoría ──────────────────────────────────────────
            $table->enum('estado_registro', ['Borrador', 'Finalizada'])->default('Finalizada');

            // Desactivación lógica: un estudio nunca se elimina físicamente
            $table->boolean('activo')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index(['patient_id', 'fecha_estudio']);
            $table->index('clinical_history_id');
            $table->index('estado_registro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doppler_reports');
    }
};
