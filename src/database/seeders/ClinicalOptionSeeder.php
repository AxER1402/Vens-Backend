<?php

namespace Database\Seeders;

use App\Models\ClinicalOption;
use Illuminate\Database\Seeder;

class ClinicalOptionSeeder extends Seeder
{
    /**
     * Catálogo de valores permitidos en las listas de selección múltiple de la
     * historia clínica. Refleja exactamente las opciones que muestra el
     * formulario de Historia Clínica Especializada del frontend.
     */
    public function run(): void
    {
        $catalogo = [
            'zonas_pierna' => [
                'Muslo', 'Pantorrilla', 'Tobillo', 'Pies', 'Otro',
            ],
            'sintomas' => [
                'Adormecimiento', 'Cansancio', 'Calambres', 'Picazón',
                'Manchas en la piel', 'Pesadez', 'Hinchazón', 'Úlceras', 'Asintomática',
            ],
            'sintomas_aumentan' => [
                'Estar de pie', 'Calor', 'Ejercicio', 'Menstruación', 'Reposo',
            ],
            'sintomas_disminuyen' => [
                'Elevación de las piernas', 'Medias compresivas', 'Ejercicio',
                'Medicamentos', 'Reposo',
            ],
            'enfermedades' => [
                'Enfermedades del corazón', 'Diabetes', 'Lumbalgia', 'Artritis', 'VIH',
                'Alta o baja presión', 'Fiebre Reumática', 'Ciática', 'Anemia', 'Otros',
            ],
            'ceap_diagnostico' => [
                'Primaria', 'Secundaria', 'Superficial', 'Profunda',
                'Perforantes', 'Mixtas', 'Reflujo', 'Obstrucción',
            ],
            'tx_zonas' => [
                'Telangiectasias', 'Reticulares', 'Varicosas trunculares', 'Perforantes',
            ],
            'indicaciones' => [
                'Venotónico: Perivasc 950/50', 'AINEs', 'Crema', 'Medias Compresivas',
                'No se prescribe tratamiento adicional',
            ],
            'observaciones' => [
                'Buena respuesta', 'Pigmentación', 'Inflamación', 'Flebitis superficial',
                'Sin complicaciones', 'Matting', 'Nódulo esclerosado',
                'Úlcera esclerosante', 'Eritema leve', 'Dolor', 'Recanalización',
            ],
        ];

        foreach ($catalogo as $categoria => $valores) {
            foreach ($valores as $orden => $valor) {
                ClinicalOption::updateOrCreate(
                    ['categoria' => $categoria, 'valor' => $valor],
                    ['orden' => $orden, 'activo' => true]
                );
            }
        }
    }
}
