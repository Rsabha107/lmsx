<?php

/**
 * Job Operations Routes
 * All routes related to job management, checkpoints, and mobile operations
 */

use App\Http\Controllers\LmsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    
    // Job Listing & Detail
    Route::get('/jobs', [LmsController::class, 'jobs'])->name('jobs');
    Route::get('/job/{id}', [LmsController::class, 'jobDetail'])->name('job.detail');
    
    // Mobile Job Views
    Route::get('/jobs/mobile', [LmsController::class, 'jobsMobile'])->name('jobs.mobile');
    Route::get('/jobs/mobile/{id}', [LmsController::class, 'jobMobileDetail'])->name('jobs.mobile.detail');
    
    // Job Status Management
    Route::post('/jobs/{jobId}/status', [LmsController::class, 'updateJobStatus'])->name('job.updateStatus');
    
    // Checkpoint Operations
    Route::prefix('jobs/checkpoint/{checkpointId}')->group(function () {
        Route::post('/complete', [LmsController::class, 'completeCheckpoint'])->name('checkpoint.complete');
        Route::post('/override', [LmsController::class, 'overrideCheckpoint'])->name('checkpoint.override');
        Route::get('/photo', [LmsController::class, 'getCheckpointPhoto'])->name('checkpoint.photo');
        Route::get('/signature', [LmsController::class, 'getCheckpointSignature'])->name('checkpoint.signature');
    });
    
    // Tracker
    Route::get('/tracker', [LmsController::class, 'tracker'])->name('tracker');
});
