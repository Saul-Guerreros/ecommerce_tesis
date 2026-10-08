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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],
	
	'gemini' => [
        'key'          => env('GEMINI_API_KEY'),
        'model'        => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
        // Modelos a probar si el principal falla, separados por coma.
        'respaldo'     => env('GEMINI_MODELOS_RESPALDO', 'gemini-2.5-flash,gemini-3.1-flash-lite-preview'),
        'thinking'     => env('GEMINI_THINKING', 'minimal'),
        // Segundos máximos por modelo y para todos los intentos juntos.
        'timeout'      => (int) env('GEMINI_TIMEOUT', 6),
        'tiempo_total' => (int) env('GEMINI_TIEMPO_TOTAL', 20),
        // El chat envía peticiones más pesadas que el buscador (historial + herramientas).
        'chat_timeout' => (int) env('GEMINI_CHAT_TIMEOUT', 15),
        'chat_tiempo_total' => (int) env('GEMINI_CHAT_TIEMPO_TOTAL', 25),
    ],

];
