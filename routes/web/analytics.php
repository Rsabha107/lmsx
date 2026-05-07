<?php

/**
 * Analytics Routes
 * Routes for analytics dashboard and data export
 */

use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    Route::get('/export', [AnalyticsController::class, 'export'])->name('export');
});
