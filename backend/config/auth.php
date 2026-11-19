<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    /*
    | Password broker is only used by staff accounts. Readers authenticate
    | exclusively through Google OAuth and never hold a password hash.
    */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => (int) env('AUTH_PASSWORD_RESET_EXPIRE_MINUTES', 30),
            'throttle' => (int) env('AUTH_PASSWORD_RESET_THROTTLE_SECONDS', 60),
        ],
    ],

    'password_timeout' => (int) env('AUTH_PASSWORD_CONFIRM_TIMEOUT', 10800),

];
