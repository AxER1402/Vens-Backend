<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Clave y valor, y no una columna por ajuste. Los datos que guarda —el
     * nombre de la clínica, el NIT, el horario— no se consultan ni se cruzan
     * entre sí: se leen todos de una vez al arrancar y se escriben todos de
     * una vez al guardar el formulario. Una tabla ancha obligaría a una
     * migración cada vez que aparezca un ajuste nuevo, sin dar nada a cambio.
     *
     * El valor va en json para que quepa tanto un texto como el horario
     * semanal, que es una estructura.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
