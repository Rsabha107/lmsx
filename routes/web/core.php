<?php

/**
 * Core Application Routes
 * Dashboard, schedule, library, and general app features
 */

use App\Http\Controllers\LmsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    
    // Core Views
    Route::get('/', [LmsController::class, 'dashboard'])->name('dashboard');
    Route::get('/schedule', [LmsController::class, 'schedule'])->name('schedule');
    Route::get('/library', [LmsController::class, 'library'])->name('library');
    
    // Communication & Monitoring
    Route::get('/notifications', [LmsController::class, 'notifications'])->name('notifications');
    Route::get('/email', [LmsController::class, 'email'])->name('email');
    Route::get('/audit', [LmsController::class, 'audit'])->name('audit');
});
