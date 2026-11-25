<?php

return [
    'name' => env('APP_NAME', 'Laravel'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => 'America/Sao_Paulo',

    'locale' => env('APP_LOCALE', 'pt_BR'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'aquarela' => [
        'api' => env('AQUARELA_API_URL', 'https://aquarelaapi.dev.br/api/reas'),

        'site' => env('AQUARELA_SITE_URL', 'https://aquarela.app.br'),
    ],

    'mecred' => [
        'api' => env('MECRED_API_URL', 'https://mecred.prod.c3sl.ufpr.br/v1/api/public/elastic/search'),

        'site' => env('MECRED_SITE_URL', 'https://mecred.mec.gov.br'),
    ],
];
