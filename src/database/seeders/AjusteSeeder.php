<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Ajustes\Ajustes;
use Illuminate\Database\Seeder;

/**
 * Siembra los ajustes con lo que hoy traen config/reportes.php y
 * config/facturacion.php.
 *
 * Sin esto, la pantalla de configuración abriría con todas las casillas
 * vacías la primera vez y parecería que la clínica no tiene nombre, cuando lo
 * que pasa es que aún no se ha guardado nada en la base.
 *
 * Solo escribe las claves que falten: pasar el seeder otra vez sobre una
 * instalación en uso no debe deshacer lo que se ajustó desde la pantalla.
 */
class AjusteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Ajustes::CAMPOS as $clave => $campo) {
            $valor = config($campo['config']);

            if ($valor === null || $valor === '') {
                continue;
            }

            Setting::firstOrCreate(['clave' => $clave], ['valor' => $valor]);
        }

        Ajustes::olvidar();
    }
}
