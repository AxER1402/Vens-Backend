<?php

/*
|--------------------------------------------------------------------------
| Reportes clínicos imprimibles
|--------------------------------------------------------------------------
|
| Datos del membrete y ajustes de página de los documentos que el sistema emite
| (historia clínica, Ecodöppler y mapeo venoso).
|
| Todo lo que puede cambiar sin tocar el diseño vive aquí y se lee del .env: el
| nombre del centro, la dirección o el número de colegiado del médico no deberían
| obligar a editar una plantilla Blade.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Centro médico
    |--------------------------------------------------------------------------
    */
    'centro' => [
        'nombre' => env('REPORTE_CENTRO_NOMBRE', 'CLÍNICA DOCTORA YOJANA MENDOZA'),
        'especialidad' => env('REPORTE_CENTRO_ESPECIALIDAD', 'FLEBOLOGÍA'),
        'direccion' => env('REPORTE_CENTRO_DIRECCION', ''),
        'telefono' => env('REPORTE_CENTRO_TELEFONO', ''),
        'correo' => env('REPORTE_CENTRO_CORREO', ''),

        // Ruta relativa a public/. mPDF lee el archivo del disco: pasarle una URL
        // provoca una petición HTTP que además falla si APP_URL no resuelve
        // desde dentro del contenedor.
        'logo' => env('REPORTE_CENTRO_LOGO', 'img/isotipo.png'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Médico responsable
    |--------------------------------------------------------------------------
    |
    | Se imprime en el pie de firma. Si se deja vacío, el reporte firma con el
    | usuario que registró la consulta, que es el dato que sí está en la base.
    |
    */
    'medico' => [
        'nombre' => env('REPORTE_MEDICO_NOMBRE', ''),
        'colegiado' => env('REPORTE_MEDICO_COLEGIADO', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Página
    |--------------------------------------------------------------------------
    |
    | El mapeo venoso va apaisado porque su plantilla es claramente horizontal
    | (1450 × 848); los otros dos son formularios de texto en vertical.
    |
    | Márgenes en milímetros. `margen_superior` deja sitio al membrete, que mPDF
    | repite en todas las páginas.
    |
    */
    'pagina' => [
        'formato' => 'A4',
        'margen_izquierdo' => 14,
        'margen_derecho' => 14,
        'margen_superior' => 30,
        'margen_inferior' => 18,
        'margen_encabezado' => 8,
        'margen_pie' => 9,
    ],

    /*
    |--------------------------------------------------------------------------
    | Marca de agua de los borradores
    |--------------------------------------------------------------------------
    |
    | Una consulta sin cerrar se puede imprimir, pero no debe poder confundirse
    | con un informe firmado.
    |
    */
    'borrador' => [
        'texto' => 'BORRADOR',
        'opacidad' => 0.08,
    ],

    /*
    |--------------------------------------------------------------------------
    | Directorio temporal de mPDF
    |--------------------------------------------------------------------------
    |
    | mPDF necesita escribir mientras compone el documento. El contenedor corre
    | como www-data (no root), así que el temporal va dentro de storage/, que ya
    | es escribible, y no en el /tmp de la imagen.
    |
    */
    'temp_dir' => storage_path('app/mpdf'),

];
