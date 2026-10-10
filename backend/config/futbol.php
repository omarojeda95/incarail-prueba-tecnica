<?php

return [

    /*
    |--------------------------------------------------------------------------
    | football-data.org
    |--------------------------------------------------------------------------
    |
    | El token es personal y solo vive en el entorno (.env). El plan gratuito
    | permite 10 peticiones por minuto y solo da acceso a las ligas "TIER_ONE".
    |
    */

    'url_base' => env('FOOTBALL_DATA_BASE_URL', 'https://api.football-data.org/v4'),

    'token' => env('FOOTBALL_DATA_TOKEN'),

    'tiempo_espera' => (int) env('FOOTBALL_DATA_TIMEOUT', 10),

    // Presupuesto de peticiones salientes, compartido por todos los procesos.
    'limite_peticiones' => [
        'maximo' => (int) env('FOOTBALL_DATA_MAX_REQUESTS_PER_MINUTE', 10),
        'ventana_segundos' => 60,
        'almacen_cache' => env('FOOTBALL_DATA_RATE_LIMIT_STORE'),
    ],

    // Planes que nuestro token puede leer. Las demás ligas se listan pero no se importan.
    'planes_disponibles' => array_filter(explode(',', env('FOOTBALL_DATA_AVAILABLE_PLANS', 'TIER_ONE'))),

    // Tiempo durante el cual los datos locales se consideran vigentes.
    'vigencia' => [
        'catalogo_minutos' => (int) env('FOOTBALL_COMPETITIONS_TTL_MINUTES', 1440),
        'detalle_liga_minutos' => (int) env('FOOTBALL_COMPETITION_DETAIL_TTL_MINUTES', 720),
    ],

];
