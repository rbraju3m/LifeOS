<?php

use Illuminate\Support\Facades\Route;

// API-only backend: the React SPA in /frontend is served separately by Nginx.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => '/api/v1',
]));
