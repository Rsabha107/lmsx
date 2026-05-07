<?php

/**
 * Example Routes for Checkpoint & Movement Template System
 * 
 * Add these to your routes/web.php file
 */

use App\Http\Controllers\Admin\CheckpointController;
use App\Http\Controllers\Admin\CheckpointTemplateController;
use App\Http\Controllers\Admin\MovementTemplateController;
use App\Http\Controllers\PlanManagementController;
use App\Http\Controllers\JobOperationController;
use App\Models\MovementTemplate;
use Illuminate\Support\Facades\Route;

// Plans Management
Route::prefix('plans')->name('plans.')->group(function () {
    // Plan CRUD
    Route::get('/', [PlanManagementController::class, 'index'])->name('index');
    Route::get('/create', [PlanManagementController::class, 'create'])->name('create');
    Route::post('/', [PlanManagementController::class, 'store'])->name('store');
    Route::get('/{plan}', [PlanManagementController::class, 'show'])->name('show');
    Route::put('/{plan}', [PlanManagementController::class, 'update'])->name('update');
    Route::delete('/{plan}', [PlanManagementController::class, 'destroy'])->name('destroy');

    // Plan Actions
    Route::post('/{plan}/generate-jobs', [PlanManagementController::class, 'generateJobs'])->name('generate-jobs');
    Route::post('/{plan}/movements', [PlanManagementController::class, 'addMovement'])->name('add-movement');
    Route::put('/{plan}/status', [PlanManagementController::class, 'updateStatus'])->name('update-status');
});

// Movement Management
Route::prefix('movements')->name('movements.')->group(function () {
    Route::put('/{movement}/checkpoint-template', [PlanManagementController::class, 'updateCheckpointTemplate'])->name('update-checkpoint-template');
    Route::put('/{movement}', [PlanManagementController::class, 'updateMovement'])->name('update');
    Route::delete('/{movement}', [PlanManagementController::class, 'deleteMovement'])->name('delete');
});

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

// API Routes for Templates
Route::prefix('api')->name('api.')->group(function () {
    // Checkpoint Templates
    Route::get('/checkpoint-templates', [PlanManagementController::class, 'getCheckpointTemplates'])->name('checkpoint-templates');
    Route::get('/checkpoint-templates/{template}', [PlanManagementController::class, 'previewCheckpointTemplate'])->name('checkpoint-template.show');
    
    // Movement Templates
    Route::get('/movement-templates', function () {
        return response()->json([
            'templates' => MovementTemplate::active()
                ->with('legs.checkpointTemplate')
                ->get()
        ]);
    })->name('movement-templates');
    
    Route::get('/movement-templates/{template}', function (MovementTemplate $template) {
        $template->load('legs.checkpointTemplate.checkpoints');
        return response()->json(['template' => $template]);
    })->name('movement-template.show');

    // Mobile API for field operations
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/my-jobs', [JobOperationController::class, 'myJobs'])->name('my-jobs');
        Route::post('/checkpoints/{checkpoint}/quick-complete', [JobOperationController::class, 'quickCompleteCheckpoint'])->name('checkpoint.quick-complete');
        Route::get('/jobs/{job}/progress', [JobOperationController::class, 'progress'])->name('job.progress');
    });
});

// Admin Routes (Template Management)
// Note: Middleware commented out for testing. Add back: ->middleware(['auth', 'admin'])
Route::prefix('admin')->name('admin.')->group(function () {
    // Checkpoint Library
    Route::resource('checkpoints', CheckpointController::class);
    
    // Checkpoint Templates
    Route::resource('checkpoint-templates', CheckpointTemplateController::class);
    Route::post('checkpoint-templates/{template}/checkpoints', [CheckpointTemplateController::class, 'attachCheckpoint'])->name('checkpoint-templates.attach-checkpoint');
    Route::delete('checkpoint-templates/{template}/checkpoints/{checkpoint}', [CheckpointTemplateController::class, 'detachCheckpoint'])->name('checkpoint-templates.detach-checkpoint');
    
    // Movement Templates
    Route::resource('movement-templates', MovementTemplateController::class);
    Route::post('movement-templates/{template}/legs', [MovementTemplateController::class, 'addLeg'])->name('movement-templates.add-leg');
    Route::put('movement-templates/{template}/legs/{leg}', [MovementTemplateController::class, 'updateLeg'])->name('movement-templates.update-leg');
    Route::delete('movement-templates/{template}/legs/{leg}', [MovementTemplateController::class, 'removeLeg'])->name('movement-templates.remove-leg');
});
