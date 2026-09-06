<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Aquí se configuran los ajustes de CORS para permitir solicitudes
    | provenientes de aplicaciones Frontend (React, Next.js, Vue, etc.).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Sin exponer Content-Disposition, el navegador no puede leer el nombre con
    // el que el servidor bautiza un informe descargado: fetch/axios lo oculta y
    // el archivo se guardaría con el nombre de la ruta en vez de
    // "historia-clinica_maria-portillo_2026-08-28.pdf".
    //
    // Las de sesión corren la misma suerte: el frontend arma su cuenta atrás
    // con ellas, y si el navegador se las esconde la sesión aparentaría vencer
    // a la hora del inicio aunque el usuario siguiera trabajando.
    'exposed_headers' => ['Content-Disposition', 'X-Session-Expires-In', 'X-Session-Expires-At'],

    'max_age' => 0,

    'supports_credentials' => true,

];
