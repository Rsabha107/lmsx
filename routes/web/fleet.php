<?php

/**
 * Fleet Management Routes
 * Routes for managing fleet, teams, and contacts
 */

use App\Http\Controllers\EventTeamsController;
use App\Http\Controllers\KitTruckDashboardController;
use App\Http\Controllers\LmsController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\MatchImportController;
use App\Http\Controllers\TeamImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    
    // Fleet Overview
    Route::get('/fleet', [LmsController::class, 'fleet'])->name('fleet');

    // Movement Tracking Dashboard
    Route::get('/kit-truck', [KitTruckDashboardController::class, 'index'])->name('kit-truck');
    
    // Matches Management
    Route::prefix('matches')->name('matches.')->group(function () {
        Route::get('/', [MatchesController::class, 'index'])->name('index');
        Route::get('/import-template', [MatchImportController::class, 'template'])->name('import-template');
        Route::post('/', [MatchesController::class, 'store'])->name('store');
        Route::put('/{id}', [MatchesController::class, 'update'])->name('update');
        Route::delete('/{id}', [MatchesController::class, 'destroy'])->name('destroy');
    });
    
    // Event Teams Management
    Route::get('/event-teams', [EventTeamsController::class, 'index'])->name('event-teams');
    Route::get('/event-teams/import-template', [TeamImportController::class, 'template'])->name('event-teams.import-template');
    
    // Contacts Management
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [LmsController::class, 'contacts'])->name('index');
        Route::post('/', [LmsController::class, 'storeContact'])->name('store');
        Route::put('/{id}', [LmsController::class, 'updateContact'])->name('update');
        Route::delete('/{id}', [LmsController::class, 'destroyContact'])->name('destroy');
    });
});
