<?php

// Titles for API error codes (docs/blueprint/12-api-specification.md §12.1).
return [
    'bad_request' => 'The request could not be understood.',
    'unauthenticated' => 'You are not signed in.',
    'forbidden' => 'You do not have permission to do this.',
    'not_found' => 'The requested item was not found.',
    'method_not_allowed' => 'This action is not supported here.',
    'conflict' => 'This item was changed by someone else. Reload and try again.',
    'payload_too_large' => 'The upload is too large.',
    'unsupported_media_type' => 'This file type is not supported.',
    'csrf_mismatch' => 'Your session expired. Please refresh the page and try again.',
    'validation_failed' => 'Some of the information you entered is not valid.',
    'account_locked' => 'Your account is temporarily locked. Please try again later.',
    'rate_limited' => 'Too many requests. Please wait a moment and try again.',
    'account_suspended' => 'This account has been suspended. Please contact support.',
    'password_confirmation_required' => 'Please confirm your password to continue.',
    'entitlement_exceeded' => 'You have reached the limit of your current plan.',
    'version_conflict' => 'This item was changed by someone else. Reload and try again.',
    'idempotency_key_invalid' => 'The Idempotency-Key header must be a UUID or ULID.',
    'idempotency_key_reused' => 'This Idempotency-Key was already used for a different request.',
    'idempotency_request_in_progress' => 'A request with this Idempotency-Key is still being processed.',
    'server_error' => 'Something went wrong on our side. Please try again.',
    'service_unavailable' => 'LifeOS is temporarily unavailable. Please try again soon.',
    'http_error' => 'The request could not be completed.',
];
