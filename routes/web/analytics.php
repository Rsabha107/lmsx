<?php

/**
 * Analytics Routes
 * Routes for analytics dashboard and data export
 */

use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

// Analytics reports across every event, so it is an oversight capability.
Route::middleware(['auth', 'permission:analytics.view'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    Route::get('/export', [AnalyticsController::class, 'export'])->name('export');
});
