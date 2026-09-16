<?php

/**
 * Fleet Management Routes
 * Routes for managing fleet, teams, and contacts
 */

use App\Http\Controllers\BaseCampHotelController;
use App\Http\Controllers\EventTeamsController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\KitTruckDashboardController;
use App\Http\Controllers\LmsController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\MatchImportController;
use App\Http\Controllers\TeamImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    
    // Fleet Overview
    Route::get('/fleet', [LmsController::class, 'fleet'])->name('fleet');

    Route::prefix('fleet')->name('fleet.')->group(function () {
        Route::post('/vehicles', [FleetController::class, 'storeVehicle'])->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', [FleetController::class, 'updateVehicle'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [FleetController::class, 'destroyVehicle'])->name('vehicles.destroy');

        Route::post('/drivers', [FleetController::class, 'storeDriver'])->name('drivers.store');
        Route::put('/drivers/{driver}', [FleetController::class, 'updateDriver'])->name('drivers.update');
        Route::delete('/drivers/{driver}', [FleetController::class, 'destroyDriver'])->name('drivers.destroy');

        Route::post('/providers', [FleetController::class, 'storeProvider'])->name('providers.store');
        Route::put('/providers/{provider}', [FleetController::class, 'updateProvider'])->name('providers.update');
        Route::delete('/providers/{provider}', [FleetController::class, 'destroyProvider'])->name('providers.destroy');
    });

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

    // Base Camp Hotels Management
    Route::prefix('base-camp-hotels')->name('base-camp-hotels.')->group(function () {
        Route::get('/', [BaseCampHotelController::class, 'index'])->name('index');
        Route::post('/', [BaseCampHotelController::class, 'store'])->name('store');
        Route::put('/{id}', [BaseCampHotelController::class, 'update'])->name('update');
        Route::delete('/{id}', [BaseCampHotelController::class, 'destroy'])->name('destroy');
    });
});
