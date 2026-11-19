<?php

use App\Modules\Spaces\Http\Controllers\SpaceController;
use Illuminate\Support\Facades\Route;

// Mounted under /api/v1 (docs/blueprint/12 §12.4). Family-space management comes in Phase 5.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('spaces', [SpaceController::class, 'index'])->name('spaces.index');
    Route::get('spaces/{space}', [SpaceController::class, 'show'])->name('spaces.show');
});
