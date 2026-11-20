<?php

declare(strict_types=1);

return [

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Reader authentication — Google OpenID Connect
    |--------------------------------------------------------------------------
    */
    'google' => [
        'scopes' => ['openid', 'profile', 'email'],

        'state_ttl_seconds' => (int) env('GOOGLE_STATE_TTL', 600),

        'success_redirect' => env('GOOGLE_SUCCESS_REDIRECT', env('FRONTEND_URL', 'http://localhost:5173').'/auth/callback'),

        'failure_redirect' => env('GOOGLE_FAILURE_REDIRECT', env('FRONTEND_URL', 'http://localhost:5173').'/auth/callback'),

        // Query parameters appended to the success/failure redirect. Never put
        // access tokens, emails, or provider errors in these URLs.
        'redirect_status_param' => 'auth',
        'redirect_reason_param' => 'reason',
    ],

    /*
    |--------------------------------------------------------------------------
    | Staff authentication — password + TOTP
    |--------------------------------------------------------------------------
    */
    'staff' => [
        'roles' => ['publisher', 'administrator'],

        // Staff must confirm a TOTP factor before a full session is issued.
        'mfa_required' => (bool) env('STAFF_MFA_REQUIRED', true),

        'password_min_length' => (int) env('STAFF_PASSWORD_MIN_LENGTH', 12),

        'password_uncompromised' => (bool) env('STAFF_PASSWORD_UNCOMPROMISED', true),

        // Password login brute-force controls.
        'lockout_threshold' => (int) env('STAFF_LOCKOUT_THRESHOLD', 5),
        'lockout_minutes' => (int) env('STAFF_LOCKOUT_MINUTES', 15),

        // Short-lived, single-use login challenge (before the session exists).
        'challenge_ttl_seconds' => (int) env('STAFF_CHALLENGE_TTL', 300),
        'challenge_max_attempts' => (int) env('STAFF_CHALLENGE_MAX_ATTEMPTS', 5),

        'totp_issuer' => env('STAFF_TOTP_ISSUER', 'Minerva Digital Platform'),
        'totp_window' => (int) env('STAFF_TOTP_WINDOW', 1),
        'recovery_code_count' => 8,

        'invitation_ttl_hours' => (int) env('STAFF_INVITATION_TTL_HOURS', 72),

        // Optional allow-list for staff invitation email domains.
        'allowed_email_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('STAFF_ALLOWED_EMAIL_DOMAINS', ''))
        ))),

        // Roles an administrator may assign through the API.
        'assignable_roles' => ['participant', 'publisher', 'administrator'],

        // Protects the last administrator account from accidental lockout.
        'protect_last_administrator' => (bool) env('STAFF_PROTECT_LAST_ADMIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Publisher capability
    |--------------------------------------------------------------------------
    */
    'publisher' => [
        // "article.publish_own" is never granted by the publisher role itself;
        // it is only effective while an active publisher grant allows it.
        'direct_publish_permission' => 'article.publish_own',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization and caching
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'permission_ttl_seconds' => (int) env('PERMISSION_CACHE_TTL', 300),
        'prefix' => 'identity',
    ],

];
