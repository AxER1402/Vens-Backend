<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El mapeo venoso se archivaba solo como PNG, así que en la consulta de
     * seguimiento el médico tenía que redibujarlo entero. Esta columna guarda
     * el mismo mapeo en forma vectorial ({ version, plantilla, objetos }): pesa
     * unos pocos KB, se puede reabrir y editar, y deja los hallazgos como datos
     * consultables en lugar de píxeles.
     *
     * El PNG se conserva porque sigue siendo lo que se imprime y lo que se
     * muestra cuando la consulta ya está finalizada.
     */
    public function up(): void
    {
        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->json('mapeo_venoso_datos')
                  ->nullable()
                  ->after('mapeo_venoso_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->dropColumn('mapeo_venoso_datos');
        });
    }
};
