<?php

return [
    'registration_received' => 'Thanks! If this email can be used, your account is ready: sign in to continue. Check your inbox for a verification link.',
    'reset_link_requested' => 'If an account exists for this email, we have sent a password reset link.',
    'locked' => [
        'subject' => 'Your LifeOS account was temporarily locked',
        'line' => 'We locked sign-in to your account for :minutes minutes after several failed password attempts.',
        'advice' => 'If this was not you, we recommend resetting your password.',
        'action' => 'Reset password',
    ],
    'registration_attempt' => [
        'subject' => 'Someone tried to create a LifeOS account with your email',
        'line' => 'Someone tried to sign up for LifeOS using this email address, which already has an account.',
        'advice' => 'If it was you, just sign in. If you forgot your password, you can reset it from the sign-in page. If it was not you, you can ignore this email.',
        'action' => 'Sign in',
    ],
];
