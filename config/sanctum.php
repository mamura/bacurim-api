<?php

use App\Modules\IdentityAccess\Interfaces\Http\Middleware\ValidateSpaCsrf;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    'stateful' => array_values(array_filter(array_map('trim', explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', env('APP_ENV') === 'local' ? 'bacurim.mamura.test,api-bacurim.mamura.test' : ''))))),
    'guard' => ['web'],
    'expiration' => null,
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateSpaCsrf::class,
    ],
];
