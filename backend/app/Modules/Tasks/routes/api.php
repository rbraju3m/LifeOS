<?php

use App\Modules\Shared\Http\Middleware\Idempotency;
use App\Modules\Tasks\Http\Controllers\TaskController;
use App\Modules\Tasks\Http\Controllers\TaskListController;
use Illuminate\Support\Facades\Route;

// Mounted under /api/v1 (docs/blueprint/12 §12.5). Children resolve only inside {space}.
Route::middleware('auth:sanctum')->scopeBindings()->prefix('spaces/{space}')->group(function () {
    Route::get('task-lists', [TaskListController::class, 'index'])->name('task-lists.index');
    Route::post('task-lists', [TaskListController::class, 'store'])->middleware(Idempotency::class)->name('task-lists.store');
    Route::get('task-lists/{taskList}', [TaskListController::class, 'show'])->name('task-lists.show');
    Route::patch('task-lists/{taskList}', [TaskListController::class, 'update'])->name('task-lists.update');
    Route::delete('task-lists/{taskList}', [TaskListController::class, 'destroy'])->name('task-lists.destroy');
    Route::post('task-lists/{taskList}/archive', [TaskListController::class, 'archive'])->name('task-lists.archive');
    Route::post('task-lists/{taskList}/unarchive', [TaskListController::class, 'unarchive'])->name('task-lists.unarchive');
    Route::post('task-lists/{taskList}/restore', [TaskListController::class, 'restore'])->withTrashed()->name('task-lists.restore');
    Route::delete('task-lists/{taskList}/force', [TaskListController::class, 'forceDestroy'])->withTrashed()->name('task-lists.force-destroy');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->middleware(Idempotency::class)->name('tasks.store');
    Route::post('tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
    Route::post('tasks/{task}/restore', [TaskController::class, 'restore'])->withTrashed()->name('tasks.restore');
    Route::delete('tasks/{task}/force', [TaskController::class, 'forceDestroy'])->withTrashed()->name('tasks.force-destroy');
});
