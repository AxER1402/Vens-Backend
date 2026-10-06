<?php

/*
|--------------------------------------------------------------------------
| Respaldos de la base de datos
|--------------------------------------------------------------------------
|
| Cada noche el comando respaldo:bd exporta la base de datos, la comprime, la
| cifra y la sube a Cloudflare R2 (disco "r2" de config/filesystems.php).
|
| Se cifra porque son historias clínicas: si alguien llega a ver el bucket, sin
| la clave solo ve archivos ilegibles. Esa clave NO está en R2, así que hay que
| guardarla también fuera del servidor (un gestor de contraseñas): sin ella, los
| respaldos no se pueden abrir.
|
*/

return [

    // Sin clave no se respalda: subir los datos sin cifrar no es una opción.
    'clave' => env('RESPALDO_CLAVE'),

    // Carpeta dentro del bucket.
    'carpeta' => env('RESPALDO_CARPETA', 'respaldos'),

    // Los respaldos más viejos que esto se borran de R2 después de subir uno
    // nuevo. 0 = no borrar nunca.
    'dias_retencion' => (int) env('RESPALDO_DIAS_RETENCION', 30),

    // Tablas de las que solo se guarda la estructura: su contenido es temporal
    // (caché, sesiones, tokens, colas) y se regenera solo.
    'sin_datos' => [
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'personal_access_tokens',
        'password_reset_tokens',
    ],

];
