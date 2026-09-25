<?php

/**
 * Utilities Routes
 *
 * Standalone data tools (file conversion and the like). None of these write to
 * the database - they produce files the user reviews and imports through the
 * normal screens.
 */

use App\Http\Controllers\UtilitiesController;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Route;

// Can be switched off entirely in Setups > Settings.
Route::middleware([
    'auth',
    'permission:fleet.manage',
    'ui_flag:' . SettingsService::FLAG_UTILITIES . ',Utilities',
])->prefix('utilities')->name('utilities.')->group(function () {
    Route::get('/', [UtilitiesController::class, 'index'])->name('index');

    Route::prefix('converters/{type}')->name('converters.')->group(function () {
        Route::get('/', [UtilitiesController::class, 'converter'])->name('show');

        // Throttled with the AI limiter: a preview may call the column-mapping agent.
        Route::post('/preview', [UtilitiesController::class, 'preview'])
            ->middleware('throttle:ai')
            ->name('preview');

        Route::post('/download', [UtilitiesController::class, 'download'])->name('download');
    });
});
