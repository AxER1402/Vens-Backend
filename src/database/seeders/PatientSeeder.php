<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patients = [
            [
                'nombre' => 'María Eugenia López',
                'edad' => 42,
                'telefono' => '50378901234',
                'lugar_residencia' => 'Colonia Escalón, San Salvador',
                'estado_civil' => 'Casado/a',
                'religion' => 'Católica',
                'estado' => 'Activo',
                'activo' => true,
            ],
            [
                'nombre' => 'Roberto Carlos Martínez',
                'edad' => 58,
                'telefono' => '50371234567',
                'lugar_residencia' => 'Santa Tecla, La Libertad',
                'estado_civil' => 'Casado/a',
                'religion' => 'Evangélica',
                'estado' => 'Seguimiento',
                'activo' => true,
            ],
            [
                'nombre' => 'Ana Beatriz Hernández',
                'edad' => 35,
                'telefono' => '50377665544',
                'lugar_residencia' => 'Antiguo Cuscatlán, La Libertad',
                'estado_civil' => 'Soltero/a',
                'religion' => 'Ninguna',
                'estado' => 'Alta',
                'activo' => true,
            ],
            [
                'nombre' => 'José Fernando Ramos',
                'edad' => 64,
                'telefono' => '50374433221',
                'lugar_residencia' => 'Soyapango, San Salvador',
                'estado_civil' => 'Viudo/a',
                'religion' => 'Católica',
                'estado' => 'Activo',
                'activo' => false, // Ejemplo de paciente inactivo/desactivado
            ],
        ];

        foreach ($patients as $patientData) {
            Patient::create($patientData);
        }
    }
}
