<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los avisos del campanario no se guardan: se calculan sobre la agenda cada
     * vez que se piden. Lo único que hay que recordar es cuáles descartó cada
     * usuario a mano, y eso es lo que guarda esta tabla.
     */
    public function up(): void
    {
        Schema::create('notification_dismissals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Qué aviso se descartó. Hoy solo hay avisos de cita ("cita:37"),
            // pero la clave admite otros orígenes sin tocar la tabla.
            $table->string('clave', 100);

            $table->timestamp('descartada_at');

            $table->timestamps();

            // Un usuario descarta un aviso una sola vez
            $table->unique(['user_id', 'clave']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_dismissals');
    }
};
