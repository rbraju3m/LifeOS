<?php

use App\Modules\Entitlements\Http\Controllers\PlanController;
use App\Modules\Entitlements\Http\Controllers\SpaceEntitlementController;
use Illuminate\Support\Facades\Route;

// Mounted under /api/v1 (docs/blueprint/12 §12.6).
Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('spaces/{space}/entitlements', [SpaceEntitlementController::class, 'show'])->name('spaces.entitlements.show');
});
