<?php

use App\Modules\Shared\Http\Middleware\Idempotency;
use App\Modules\Tags\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Mounted under /api/v1 (docs/blueprint/12 §12.5). {tag} resolves only inside {space}.
Route::middleware('auth:sanctum')->scopeBindings()->prefix('spaces/{space}')->group(function () {
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->middleware(Idempotency::class)->name('tags.store');
    Route::get('tags/{tag}', [TagController::class, 'show'])->name('tags.show');
    Route::patch('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
});
