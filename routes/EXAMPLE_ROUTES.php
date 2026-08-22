<?php

/**
 * Legacy Routes - Job Operations & Template Management
 * 
 * Note: Plans and Movements routes have been moved to routes/web/plans.php
 * 
 * Remaining routes to be refactored:
 * - Job Operations (field execution)
 * - Template Admin (checkpoints, checkpoint templates, movement templates)
 */

use App\Http\Controllers\Admin\CheckpointController;
use App\Http\Controllers\Admin\CheckpointTemplateController;
use App\Http\Controllers\Admin\MovementTemplateController;
use App\Http\Controllers\JobOperationController;
use App\Models\MovementTemplate;
use Illuminate\Support\Facades\Route;

// Job Operations (Field Execution)
Route::prefix('jobs')->name('jobs.')->group(function () {
    // Route::get('/', [JobOperationController::class, 'index'])->name('index'); // Commented out - conflicts with LmsController@jobs
    Route::get('/{job}', [JobOperationController::class, 'show'])->name('show');
    Route::post('/{job}/dispatch', [JobOperationController::class, 'dispatch'])->name('dispatch');
    Route::post('/{job}/start', [JobOperationController::class, 'start'])->name('start');
    Route::post('/{job}/complete', [JobOperationController::class, 'complete'])->name('complete');
    
    // Checkpoint Actions
    Route::post('/{job}/checkpoints/{checkpoint}/complete', [JobOperationController::class, 'completeCheckpoint'])->name('checkpoint.complete');
    Route::post('/{job}/checkpoints/{checkpoint}/skip', [JobOperationController::class, 'skipCheckpoint'])->name('checkpoint.skip');
});

// Mobile API for field operations
Route::prefix('api')->name('api.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/my-jobs', [JobOperationController::class, 'myJobs'])->name('my-jobs');
    Route::post('/checkpoints/{checkpoint}/quick-complete', [JobOperationController::class, 'quickCompleteCheckpoint'])->name('checkpoint.quick-complete');
    Route::get('/jobs/{job}/progress', [JobOperationController::class, 'progress'])->name('job.progress');
});

// Admin Routes (Template Management)
// Note: Middleware commented out for testing. Add back: ->middleware(['auth', 'admin'])
Route::prefix('admin')->name('admin.')->group(function () {
    // Checkpoint Library
    Route::delete('checkpoints/bulk-delete', [CheckpointController::class, 'destroyBulk'])->name('checkpoints.bulk-delete');
    Route::resource('checkpoints', CheckpointController::class);

    // Checkpoint Templates
    Route::get('checkpoint-templates/by-event/{event}', [CheckpointTemplateController::class, 'byEvent'])->name('checkpoint-templates.by-event');
    Route::post('checkpoint-templates/copy-from-event', [CheckpointTemplateController::class, 'copyFromEvent'])->name('checkpoint-templates.copy-from-event');
    Route::resource('checkpoint-templates', CheckpointTemplateController::class);
    Route::post('checkpoint-templates/{template}/checkpoints', [CheckpointTemplateController::class, 'attachCheckpoint'])->name('checkpoint-templates.attach-checkpoint');
    Route::delete('checkpoint-templates/{template}/checkpoints/{checkpoint}', [CheckpointTemplateController::class, 'detachCheckpoint'])->name('checkpoint-templates.detach-checkpoint');

    // Movement Templates
    Route::get('movement-templates/by-event/{event}', [MovementTemplateController::class, 'byEvent'])->name('movement-templates.by-event');
    Route::post('movement-templates/copy-from-event', [MovementTemplateController::class, 'copyFromEvent'])->name('movement-templates.copy-from-event');
    Route::resource('movement-templates', MovementTemplateController::class);
    Route::post('movement-templates/{template}/legs', [MovementTemplateController::class, 'addLeg'])->name('movement-templates.add-leg');
    Route::put('movement-templates/{template}/legs/{leg}', [MovementTemplateController::class, 'updateLeg'])->name('movement-templates.update-leg');
    Route::delete('movement-templates/{template}/legs/{leg}', [MovementTemplateController::class, 'removeLeg'])->name('movement-templates.remove-leg');
});
