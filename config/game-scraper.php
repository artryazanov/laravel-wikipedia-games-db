<?php

return [
    // MediaWiki API endpoint (defaults to English Wikipedia)
    'api_endpoint' => env('WIKIPEDIA_GAMES_DB_API_ENDPOINT', 'https://en.wikipedia.org/w/api.php'),

    // User-Agent header sent with each request (please customize in your app)
    'user_agent' => env('WIKIPEDIA_GAMES_DB_USER_AGENT', 'LaravelWikipediaGamesDb/1.0 (+https://example.com; contact@example.com)'),

    // Root category to start scraping from
    'root_category' => env('WIKIPEDIA_GAMES_DB_ROOT_CATEGORY', 'Category:Video games'),

    // Delay between API requests in milliseconds (throttling)
    'throttle_milliseconds' => (int) env('WIKIPEDIA_GAMES_DB_THROTTLE_MS', 1000),

    // Default per-batch page fetch limit for SyncGamesCommand and jobs
    'limit' => (int) env('WIKIPEDIA_GAMES_DB_LIMIT', 100),

    // Queue settings
    'queue_connection' => env('WIKIPEDIA_GAMES_DB_QUEUE_CONNECTION', null), // null means use the default connection
    'queue_name' => env('WIKIPEDIA_GAMES_DB_QUEUE_NAME', 'default'),
];
