<?php

use App\Modules\Audit\Http\Controllers\SecurityEventsController;
use Illuminate\Support\Facades\Route;

// Mounted under /api/v1 (docs/blueprint/12 §12.3).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me/security-events', SecurityEventsController::class)->name('me.security-events');
});
