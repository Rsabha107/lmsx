<?php

/**
 * Fleet Management Routes
 * Routes for managing fleet, teams, and contacts
 */

use App\Http\Controllers\LmsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    
    // Fleet Overview
    Route::get('/fleet', [LmsController::class, 'fleet'])->name('fleet');
    
    // Teams Management
    Route::prefix('teams')->name('teams.')->group(function () {
        Route::get('/', [LmsController::class, 'teams'])->name('index');
        Route::post('/', [LmsController::class, 'storeTeam'])->name('store');
        Route::put('/{code}', [LmsController::class, 'updateTeam'])->name('update');
        Route::delete('/{code}', [LmsController::class, 'destroyTeam'])->name('destroy');
    });
    
    // Contacts Management
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [LmsController::class, 'contacts'])->name('index');
        Route::post('/', [LmsController::class, 'storeContact'])->name('store');
        Route::put('/{id}', [LmsController::class, 'updateContact'])->name('update');
        Route::delete('/{id}', [LmsController::class, 'destroyContact'])->name('destroy');
    });
});
