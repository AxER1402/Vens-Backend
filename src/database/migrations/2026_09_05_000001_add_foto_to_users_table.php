<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Se guarda la ruta del archivo, no la imagen. La foto vive en el disco
     * público (storage/app/public/avatares) y la base solo recuerda dónde
     * quedó, que es lo que hay que saber para volver a servirla o borrarla.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_path')
                  ->nullable()
                  ->after('telefono');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto_path');
        });
    }
};
