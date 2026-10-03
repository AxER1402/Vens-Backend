<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // El servicio de WhatsApp Web (docker/whatsapp) que manda los
    // recordatorios de citas. Si activarlos o no se decide en la pantalla de
    // configuración; aquí solo está cómo llegar a él.
    'whatsapp' => [
        'url' => env('WHATSAPP_URL', 'http://whatsapp:3000'),
        'token' => env('WHATSAPP_TOKEN'),
        'codigo_pais' => env('WHATSAPP_CODIGO_PAIS', '502'),
    ],

    // Inicio de sesión con Google. Solo hace falta el Client ID: el backend no
    // habla con Google en nombre del usuario, solo comprueba que el token que
    // trae el frontend fue emitido para esta aplicación.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
