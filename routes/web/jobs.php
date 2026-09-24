<?php

/**
 * Job Operations Routes
 * All routes related to job management, checkpoints, and mobile operations
 */

use App\Http\Controllers\LmsController;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    // Desktop console views.
    Route::middleware('permission:console.view')->group(function () {
        Route::get('/jobs', [LmsController::class, 'jobs'])->name('jobs');
        Route::get('/job/{id}', [LmsController::class, 'jobDetail'])->name('job.detail');
        Route::get('/tracker', [LmsController::class, 'tracker'])->name('tracker');
    });

    // The mobile job workflow, which field supervisors are limited to. Can be
    // switched off entirely in Setups > Settings.
    Route::middleware([
        'permission:jobs.view',
        'ui_flag:' . SettingsService::FLAG_JOBS_MOBILE_MENU . ',The mobile jobs view',
    ])->group(function () {
        Route::get('/jobs/mobile', [LmsController::class, 'jobsMobile'])->name('jobs.mobile');
        Route::get('/jobs/mobile/{id}', [LmsController::class, 'jobMobileDetail'])->name('jobs.mobile.detail');
    });

    // Job Status Management
    Route::post('/jobs/{jobId}/status', [LmsController::class, 'updateJobStatus'])->name('job.updateStatus');

    // Field-reported issues
    Route::post('/job-issues/{issue}/resolve', [LmsController::class, 'resolveJobIssue'])->name('job.issues.resolve');
    
    // Checkpoint Operations
    Route::prefix('jobs/checkpoint/{checkpointId}')->group(function () {
        Route::post('/complete', [LmsController::class, 'completeCheckpoint'])->name('checkpoint.complete');
        Route::post('/override', [LmsController::class, 'overrideCheckpoint'])->name('checkpoint.override');
        Route::get('/photo', [LmsController::class, 'getCheckpointPhoto'])->name('checkpoint.photo');
        Route::get('/signature', [LmsController::class, 'getCheckpointSignature'])->name('checkpoint.signature');
    });
});
