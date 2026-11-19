<?php

use Laravel\Fortify\Features;

/*
| Fortify runs headless (no views). Its routes are NOT auto-registered
| (Fortify::ignoreRoutes() in IdentityServiceProvider); the Identity module
| mounts the controllers it uses under /api/v1/auth in its own routes file.
| See docs/blueprint/13-authentication-and-authorization-design.md.
*/
return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,

    // Where browser (non-JSON) flows such as the email verification link land.
    'home' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/').'/',
    'redirects' => [
        'email-verification' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/').'/',
    ],

    'prefix' => 'api/v1/auth',
    'domain' => null,
    'middleware' => ['api'],
    'auth_middleware' => 'auth',

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'verification' => 'verification',
    ],

    'views' => false,

    'features' => [
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            'window' => 1,
        ]),
    ],
];
