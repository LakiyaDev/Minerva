<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Laravel\Sanctum\Http\Middleware\ValidateOrigin;

return [

    /*
    | Cookie-based SPA authentication. The React client must send requests
    | with credentials and originate from a stateful domain listed below.
    */
    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url((string) env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    'guard' => ['web'],

    /*
    | Session cookies are the primary transport, so token expiration is not
    | used. If bearer tokens are enabled for trusted clients, set a low TTL.
    */
    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
        'validate_origin' => ValidateOrigin::class,
        'check_abilities' => CheckAbilities::class,
        'check_for_any_ability' => CheckForAnyAbility::class,
    ],

];
