<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // Auth is Sanctum Bearer tokens (docs/PRD.md §92), not cookie-based SPA auth,
    // so this app never needs the "sanctum/csrf-cookie" route or credentialed CORS.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Never '*' — an explicit allow-list, per docs/PRD.md §93. Comma-separated so
    // additional environments (staging, a second frontend) can be added without
    // code changes.
    'allowed_origins' => array_filter(explode(',', (string) env('FRONTEND_URLS', 'http://localhost:3000'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Accept', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
