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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->integer('edad');
            $table->string('telefono');
            $table->string('lugar_residencia');
            $table->enum('estado_civil', [
                'Soltero/a',
                'Casado/a',
                'Divorciado/a',
                'Viudo/a',
                'Unión Libre',
                'Otro'
            ])->default('Soltero/a');
            $table->string('religion')->nullable();
            $table->enum('estado', ['Activo', 'Seguimiento', 'Alta'])->default('Activo');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
