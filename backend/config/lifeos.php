<?php

return [
    // The SPA's public URL. Same origin as the API in production (docs/adr/0002).
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

    'legal' => [
        // Bump when the published documents change; stored per user in `consents`.
        'terms_version' => env('LEGAL_TERMS_VERSION', '2026-11-18'),
        'privacy_version' => env('LEGAL_PRIVACY_VERSION', '2026-11-18'),
    ],

    'auth' => [
        // docs/blueprint/13: 10 failed logins within an hour lock the account for 15 minutes.
        'lockout_failures' => 10,
        'lockout_window_minutes' => 60,
        'lockout_minutes' => 15,
        // "Remember me" lifetime.
        'remember_minutes' => 60 * 24 * 30,
    ],

    'audit' => [
        // docs/blueprint/10 §10.8: auth.* security events 2 years, other entries 1 year.
        'retention_days' => [
            'security' => 730,
            'other' => 365,
        ],
    ],
];
