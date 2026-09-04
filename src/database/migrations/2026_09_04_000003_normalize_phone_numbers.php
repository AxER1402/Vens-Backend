<?php

use App\Support\Contacto\Telefono;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dejar en dígitos los teléfonos ya guardados.
     *
     * El formulario y la API ya no admiten otra cosa, pero lo registrado antes
     * puede traer «+», guiones o espacios, y esos registros seguirían sin
     * aparecer al buscarlos escritos de otra forma. La limpieza solo quita
     * separadores: no cambia ningún número, solo cómo está escrito.
     */
    public function up(): void
    {
        foreach (['patients', 'users'] as $tabla) {
            foreach (DB::table($tabla)->select('id', 'telefono')->get() as $fila) {
                $limpio = Telefono::normalizar($fila->telefono);

                if ($limpio !== $fila->telefono) {
                    DB::table($tabla)->where('id', $fila->id)->update(['telefono' => $limpio]);
                }
            }
        }
    }

    /**
     * No se revierte: los separadores que se quitaron no se pueden adivinar,
     * y volver a ponerlos al azar sería inventar datos.
     */
    public function down(): void
    {
        //
    }
};
