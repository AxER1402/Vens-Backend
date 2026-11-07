<?php

/*
|--------------------------------------------------------------------------
| Entorno de demostración
|--------------------------------------------------------------------------
|
| Solo lo usa DemoSeeder. Los correos de las cuentas de la demo se leen del
| .env.demo del servidor y no del código, porque pueden ser correos reales
| de quien presenta y el repositorio es público. Sin definir, las cuentas
| conservan los correos de UserSeeder.
|
*/

return [
    'correos' => [
        'administrador' => env('DEMO_CORREO_ADMIN') ?: 'admin@vens.com',
        'medico' => env('DEMO_CORREO_MEDICO') ?: 'medico@vens.com',
        'recepcionista' => env('DEMO_CORREO_RECEPCION') ?: 'recepcion@vens.com',
    ],
];
