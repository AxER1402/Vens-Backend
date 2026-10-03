<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El identificador que Google le da a la cuenta (el "sub" del token). Se
     * guarda la primera vez que alguien entra con Google y desde entonces es
     * lo que lo reconoce: el correo de una cuenta de Google puede cambiar, este
     * número no.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')
                  ->nullable()
                  ->unique()
                  ->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
