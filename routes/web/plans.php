<?php

/**
 * Plan Management Routes
 * 
 * Routes for creating, managing, and executing logistics plans.
 * Plans contain movements which are converted to executable jobs.
 */

use App\Http\Controllers\ConflictController;
use App\Http\Controllers\MovementCrewController;
use App\Http\Controllers\PlanManagementController;
use App\Http\Controllers\ResourceScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    Route::get('/crew-assignment', [MovementCrewController::class, 'index'])
        ->middleware('permission:movements.assign-crew')
        ->name('crew-assignment');

    Route::middleware('permission:movements.view')->group(function () {
        Route::get('/resource-schedule', [ResourceScheduleController::class, 'index'])->name('resource-schedule');
        Route::get('/resource-schedule/export', [ResourceScheduleController::class, 'export'])->name('resource-schedule.export');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Plans Management
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('plans')->name('plans.')->group(function () {
        Route::middleware('permission:plans.view')->group(function () {
            Route::get('/', [PlanManagementController::class, 'index'])->name('index');
            Route::get('/create', [PlanManagementController::class, 'create'])->name('create');
            Route::get('/{plan}', [PlanManagementController::class, 'show'])->name('show');
        });

        Route::middleware('permission:plans.manage')->group(function () {
            Route::post('/bulk', [PlanManagementController::class, 'bulkStore'])->name('bulk-store');
            Route::post('/bulk-matches', [PlanManagementController::class, 'bulkMatchStore'])->name('bulk-match-store');
            Route::post('/', [PlanManagementController::class, 'store'])->name('store');
            Route::put('/{plan}', [PlanManagementController::class, 'update'])->name('update');
            Route::get('/{plan}/delete-check', [PlanManagementController::class, 'deleteCheck'])->name('delete-check');
            Route::delete('/bulk-delete', [PlanManagementController::class, 'destroyBulk'])->name('bulk-destroy');
            Route::delete('/{plan}', [PlanManagementController::class, 'destroy'])->name('destroy');

            // Plan Actions
            Route::post('/{plan}/generate-jobs', [PlanManagementController::class, 'generateJobs'])->name('generate-jobs');
            Route::post('/{plan}/movements', [PlanManagementController::class, 'addMovement'])->name('add-movement');
            Route::put('/{plan}/status', [PlanManagementController::class, 'updateStatus'])->name('update-status');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Movement Management
    |--------------------------------------------------------------------------
    */

    Route::prefix('movements')->name('movements.')->group(function () {
        Route::get('/{movement}/checkpoints', [PlanManagementController::class, 'checkpoints'])
            ->middleware('permission:plans.view')
            ->name('checkpoints');

        Route::get('/{movement}/crew-options', [ConflictController::class, 'crewOptions'])
            ->middleware('permission:plans.view')
            ->name('crew-options');

        Route::patch('/{movement}/crew', [MovementCrewController::class, 'update'])
            ->middleware('permission:movements.assign-crew|plans.manage')
            ->name('assign-crew');

        Route::middleware('permission:plans.manage')->group(function () {
            Route::post('/{movement}/recompute-window', [ConflictController::class, 'recomputeWindow'])->name('recompute-window');
            Route::delete('/bulk-delete', [PlanManagementController::class, 'deleteMovementsBulk'])->name('bulk-delete');
            Route::put('/{movement}/checkpoint-template', [PlanManagementController::class, 'updateCheckpointTemplate'])->name('update-checkpoint-template');
            Route::put('/{movement}', [PlanManagementController::class, 'updateMovement'])->name('update');
            Route::delete('/{movement}', [PlanManagementController::class, 'deleteMovement'])->name('delete');
        });
    });

    Route::prefix('conflicts')->name('conflicts.')->middleware('permission:plans.manage')->group(function () {
        Route::post('/accept', [ConflictController::class, 'accept'])->name('accept');
        Route::delete('/accept', [ConflictController::class, 'reopen'])->name('reopen');
    });

    /*
    |--------------------------------------------------------------------------
    | API Routes for Templates
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:plans.view')->prefix('api')->name('api.')->group(function () {
        // Duplicate Detection
        Route::post('/check-duplicate', [PlanManagementController::class, 'checkDuplicate'])->name('check-duplicate');
        Route::post('/check-duplicate-bulk', [PlanManagementController::class, 'checkDuplicateBulk'])->name('check-duplicate-bulk');
        
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
