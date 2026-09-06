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

    // Nunca '*'. Con supports_credentials en true, un comodín hace que
    // Laravel devuelva como permitido el Origin de quien pregunte, sea quien
    // sea: cualquier página web podría llamar a esta API en nombre de un
    // usuario con la sesión abierta. En un sistema de historias clínicas eso
    // no es aceptable.
    //
    // El valor por defecto es FRONTEND_URL, que es el único origen que
    // legítimamente consume esta API:
    //   - En desarrollo, http://localhost:8000, el mismo Nginx que sirve la API.
    //   - En producción con la imagen unificada, el mismo dominio de la
    //     aplicación, así que ya no hay peticiones entre orígenes que valga
    //     la pena permitir y esta lista deja de usarse.
    //
    // CORS_ALLOWED_ORIGINS solo hace falta el día que otro cliente, alojado
    // en otro dominio, tenga que consumir la API. Se listan separados por
    // comas y sin barra final.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('FRONTEND_URL', '')))
    ))),

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
