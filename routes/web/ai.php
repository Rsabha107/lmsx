<?php

/**
 * AI Operations Copilot Routes
 *
 * Read-only natural-language querying over movement/job operational data,
 * scoped by the active event and the user's functional-area permissions
 * (see App\Policies\MovementPolicy / JobOperationPolicy).
 */

use App\Http\Controllers\AiCopilotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:ai.use', 'throttle:ai'])->prefix('ai')->name('ai.')->group(function () {
    Route::get('/', [AiCopilotController::class, 'index'])->name('index');
    Route::post('/query', [AiCopilotController::class, 'query'])->name('query');
});
