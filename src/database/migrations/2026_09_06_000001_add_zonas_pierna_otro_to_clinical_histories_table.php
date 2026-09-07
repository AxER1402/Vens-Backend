<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle libre cuando se marca 'Otro' en las zonas con molestias.
     *
     * La lista ofrece muslo, pantorrilla, tobillo y pies. Quien marcaba 'Otro'
     * —rodilla, ingle, planta del pie— no tenía dónde escribir cuál, así que la
     * historia guardaba que había otra zona pero no cuál era, que es justo el
     * dato por el que se marca.
     *
     * Va igual que enfermedades_otros, que resuelve lo mismo unas preguntas más
     * abajo: una columna de texto al lado de la lista, no un elemento más del
     * catálogo.
     */
    public function up(): void
    {
        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->string('zonas_pierna_otro', 255)->nullable()->after('consulta_por');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->dropColumn('zonas_pierna_otro');
        });
    }
};
