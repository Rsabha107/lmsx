<?php

/**
 * Plan Management Routes
 * 
 * Routes for creating, managing, and executing logistics plans.
 * Plans contain movements which are converted to executable jobs.
 */

use App\Http\Controllers\PlanManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Plans Management
    |--------------------------------------------------------------------------
    */
    
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

    /*
    |--------------------------------------------------------------------------
    | Movement Management
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('movements')->name('movements.')->group(function () {
        Route::put('/{movement}/checkpoint-template', [PlanManagementController::class, 'updateCheckpointTemplate'])->name('update-checkpoint-template');
        Route::put('/{movement}', [PlanManagementController::class, 'updateMovement'])->name('update');
        Route::delete('/{movement}', [PlanManagementController::class, 'deleteMovement'])->name('delete');
    });

    /*
    |--------------------------------------------------------------------------
    | API Routes for Templates
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('api')->name('api.')->group(function () {
        // Checkpoint Templates
        Route::get('/checkpoint-templates', [PlanManagementController::class, 'getCheckpointTemplates'])->name('checkpoint-templates');
        Route::get('/checkpoint-templates/{template}', [PlanManagementController::class, 'previewCheckpointTemplate'])->name('checkpoint-template.show');
        
        // Movement Templates
        Route::get('/movement-templates', function () {
            return response()->json([
                'templates' => \App\Models\MovementTemplate::active()
                    ->with('legs.checkpointTemplate')
                    ->get()
            ]);
        })->name('movement-templates');
        
        Route::get('/movement-templates/{template}', function (\App\Models\MovementTemplate $template) {
            $template->load('legs.checkpointTemplate.checkpoints');
            return response()->json(['template' => $template]);
        })->name('movement-template.show');
    });
});
