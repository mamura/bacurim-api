<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_ENV') === 'local' ? 'http://bacurim.mamura.test,http://api-bacurim.mamura.test' : ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN', 'X-Requested-With'],
    'exposed_headers' => ['Retry-After'],
    'max_age' => 600,
    'supports_credentials' => true,
];
