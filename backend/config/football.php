<?php

return [

    /*
    |--------------------------------------------------------------------------
    | football-data.org
    |--------------------------------------------------------------------------
    |
    | The token is personal and must only live in the environment (.env).
    | The free plan allows 10 requests per minute and only gives access to
    | the competitions of the "TIER_ONE" plan.
    |
    */

    'base_url' => env('FOOTBALL_DATA_BASE_URL', 'https://api.football-data.org/v4'),

    'token' => env('FOOTBALL_DATA_TOKEN'),

    'timeout' => (int) env('FOOTBALL_DATA_TIMEOUT', 10),

    // Outgoing request budget shared by every process (web, worker, scheduler).
    'rate_limit' => [
        'max_requests' => (int) env('FOOTBALL_DATA_MAX_REQUESTS_PER_MINUTE', 10),
        'window_seconds' => 60,
        'cache_store' => env('FOOTBALL_DATA_RATE_LIMIT_STORE'),
    ],

    // Plans our token can read. Others are listed but cannot be imported.
    'available_plans' => array_filter(explode(',', env('FOOTBALL_DATA_AVAILABLE_PLANS', 'TIER_ONE'))),

    // How long local data is considered fresh before trying to refresh it.
    'freshness' => [
        'competitions_minutes' => (int) env('FOOTBALL_COMPETITIONS_TTL_MINUTES', 1440),
        'competition_detail_minutes' => (int) env('FOOTBALL_COMPETITION_DETAIL_TTL_MINUTES', 720),
    ],

];
