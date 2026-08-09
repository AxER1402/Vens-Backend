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
        Schema::create('clinical_histories', function (Blueprint $table) {
            $table->id();

            // Paciente dueño del expediente clínico
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->onDelete('cascade');

            // Cita en la que se levantó la historia (opcional)
            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->onDelete('set null');

            $table->date('fecha_consulta');

            // ── Interrogatorio y síntomas ────────────────────────────────────
            $table->enum('consulta_por', ['Estética', 'Enfermedad'])->nullable();
            $table->string('disminuyen_otros', 255)->nullable();

            // ── Antecedentes ─────────────────────────────────────────────────
            $table->boolean('familiar_varices')->nullable();
            $table->string('alergias', 255)->nullable();
            $table->text('cirugias')->nullable();

            // ── Antecedentes ginecológicos ───────────────────────────────────
            $table->unsignedTinyInteger('gestas')->nullable();
            $table->unsignedTinyInteger('abortos')->nullable();
            $table->unsignedTinyInteger('partos')->nullable();
            $table->unsignedTinyInteger('cesareas')->nullable();
            $table->unsignedTinyInteger('hijos_vivos')->nullable();
            $table->unsignedTinyInteger('hijos_muertos')->nullable();
            $table->date('ultima_menstruacion')->nullable();
            $table->string('hormonas', 255)->nullable();

            // Detalle libre cuando se marca 'Otros' en la lista de enfermedades
            $table->string('enfermedades_otros', 255)->nullable();

            // ── Examen físico ────────────────────────────────────────────────
            // La presión arterial se conserva como dato compuesto (ej: '120/80')
            $table->string('presion_arterial', 10)->nullable();
            $table->unsignedSmallInteger('frecuencia_cardiaca')->nullable();
            $table->unsignedSmallInteger('frecuencia_respiratoria')->nullable();
            $table->decimal('temperatura', 4, 1)->nullable();
            $table->decimal('peso', 5, 2)->nullable();
            $table->enum('ubicacion_patologia', ['MID', 'MII', 'BILATERAL'])->nullable();

            // ── Plan de tratamiento y escleroterapia ─────────────────────────
            $table->decimal('esclero_concentracion', 5, 2)->nullable();
            $table->enum('esclero_forma', ['Líquida', 'Espuma'])->nullable();
            $table->decimal('esclero_volumen', 5, 2)->nullable();
            $table->string('indicaciones_otros', 255)->nullable();

            // ── Evolución y observaciones ────────────────────────────────────
            $table->enum('evolucion', ['Mejoría', 'Igual', 'Empeoramiento'])->nullable();
            $table->enum('estado_general', [
                'Respuesta satisfactoria',
                'Requiere nuevas sesiones',
                'Requiere cirugía',
                'Sospecha de complicación',
            ])->nullable();
            $table->text('notas')->nullable();

            // ── Mapeo venoso ─────────────────────────────────────────────────
            // Solo se guarda la ruta del PNG; el archivo vive en el disco 'public'
            $table->string('mapeo_venoso_path', 255)->nullable();
            $table->timestamp('mapeo_venoso_updated_at')->nullable();

            // ── Control y auditoría ──────────────────────────────────────────
            $table->enum('estado_registro', ['Borrador', 'Finalizada'])->default('Borrador');

            // Desactivación lógica: una historia clínica nunca se elimina físicamente
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

            $table->index(['patient_id', 'fecha_consulta']);
            $table->index('estado_registro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_histories');
    }
};
