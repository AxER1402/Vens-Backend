<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'Administrador',
                'slug' => 'administrador',
                'descripcion' => 'Tiene acceso total al sistema (usuarios, configuración, reportes, pacientes, citas y módulos clínicos).',
            ],
            [
                'nombre' => 'Médico',
                'slug' => 'medico',
                'descripcion' => 'Tiene acceso a casi todo el sistema (gestión médica, expedientes, pacientes, diagnósticos y citas).',
            ],
            [
                'nombre' => 'Recepcionista',
                'slug' => 'recepcionista',
                'descripcion' => 'Tiene acceso a la agenda, recepción de pacientes y programación de citas.',
            ],
            [
                'nombre' => 'Enfermera',
                'slug' => 'enfermera',
                'descripcion' => 'Tiene acceso a apoyo médico, asistencia en procedimientos y toma de signos vitales.',
            ],
            [
                'nombre' => 'Paciente',
                'slug' => 'paciente',
                'descripcion' => 'Acceso exclusivo al portal del paciente para consultar citas, indicaciones e historial personal.',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }
    }
}
