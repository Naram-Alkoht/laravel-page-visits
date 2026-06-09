<?php

declare(strict_types=1);

return [
    'enabled' => env('PAGE_VISITS_ENABLED', true),
    'redis_connection' => env('PAGE_VISITS_REDIS_CONNECTION', 'cache'),
    'redis_prefix' => env('PAGE_VISITS_REDIS_PREFIX', 'page-visits'),
    'default_ttl_seconds' => (int) env('PAGE_VISITS_DEFAULT_TTL_SECONDS', 60 * 60 * 6),
    'route_ttls' => [],
    'pending_ttl_seconds' => (int) env('PAGE_VISITS_PENDING_TTL_SECONDS', 60 * 60 * 24 * 3),
    'exclude_authenticated_users' => false,
    'ignore_bots' => true,
    'bot_user_agent_fragments' => [
        'bot',
        'crawl',
        'spider',
        'slurp',
        'facebookexternalhit',
        'preview',
    ],

    /*
     * Fully-qualified model classes that may be tracked as morphable visitables.
     * Add the models you want to count detail-page visits for in the host app.
     */
    'visitable_types' => [],

    /*
     * The endpoint the front-end tracker posts to.
     */
    'route' => [
        'uri' => env('PAGE_VISITS_ROUTE_URI', '/page-visits'),
        'name' => 'page-visits.store',
        'middleware' => ['web', 'throttle:60,1'],
    ],
];
