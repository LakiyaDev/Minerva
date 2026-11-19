<?php

declare(strict_types=1);

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('FRONTEND_URL', 'http://localhost:5173')))
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Never use a wildcard origin while supports_credentials is true.
    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Requested-With', 'X-Request-ID', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN'],

    'exposed_headers' => ['X-Request-ID', 'Retry-After'],

    'max_age' => 0,

    'supports_credentials' => true,

];
