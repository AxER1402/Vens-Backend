<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Solo los catálogos: roles, opciones clínicas y ajustes de fábrica.
 *
 * Es lo que corre `make prod-seed` sobre una base de producción. A
 * diferencia de DatabaseSeeder no siembra pacientes de ejemplo, que
 * PatientSeeder crearía de nuevo en cada corrida mezclados con los reales,
 * y solo crea las cuentas iniciales cuando la base no tiene ninguna: sobre
 * una base en uso, UserSeeder devolvería a esas cuentas la contraseña
 * publicada en el README. Se puede repetir sin cambiar nada que ya exista.
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ClinicalOptionSeeder::class,
            AjusteSeeder::class,
        ]);

        // Instalación nueva: sin cuentas nadie puede iniciar sesión
        if (! User::exists()) {
            $this->call(UserSeeder::class);
        }
    }
}
