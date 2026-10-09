<?php

/**
 * Fleet Management Routes
 * Routes for managing fleet, teams, and contacts
 */

use App\Http\Controllers\AirportController;
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

    // Read-only pages.
    Route::middleware('permission:fleet.view')->group(function () {
        Route::get('/fleet', [LmsController::class, 'fleet'])->name('fleet');
        Route::get('/kit-truck', [KitTruckDashboardController::class, 'index'])->name('kit-truck');
        Route::get('/matches', [MatchesController::class, 'index'])->name('matches.index');
        Route::get('/matches/import-template', [MatchImportController::class, 'template'])->name('matches.import-template');
        Route::get('/event-teams', [EventTeamsController::class, 'index'])->name('event-teams');
        Route::get('/event-teams/import-template', [TeamImportController::class, 'template'])->name('event-teams.import-template');
        Route::get('/contacts', [LmsController::class, 'contacts'])->name('contacts.index');
        Route::get('/base-camp-hotels', [BaseCampHotelController::class, 'index'])->name('base-camp-hotels.index');
        Route::get('/airports', [AirportController::class, 'index'])->name('airports.index');
    });

    // The crew agency maintains its own vehicles, drivers and providers, but no other master data.
    Route::middleware('permission:fleet.manage|fleet.manage-resources')
        ->prefix('fleet')->name('fleet.')->group(function () {
            Route::post('/vehicles', [FleetController::class, 'storeVehicle'])->name('vehicles.store');
            Route::put('/vehicles/{vehicle}', [FleetController::class, 'updateVehicle'])->name('vehicles.update');
            Route::delete('/vehicles/{vehicle}', [FleetController::class, 'destroyVehicle'])->name('vehicles.destroy');

            Route::post('/drivers', [FleetController::class, 'storeDriver'])->name('drivers.store');
            Route::put('/drivers/{driver}', [FleetController::class, 'updateDriver'])->name('drivers.update');
            Route::delete('/drivers/{driver}', [FleetController::class, 'destroyDriver'])->name('drivers.destroy');

            Route::post('/providers', [FleetController::class, 'storeProvider'])->name('providers.store');
            Route::post('/pool', [FleetController::class, 'setPool'])->name('pool');
            Route::put('/providers/{provider}', [FleetController::class, 'updateProvider'])->name('providers.update');
            Route::delete('/providers/{provider}', [FleetController::class, 'destroyProvider'])->name('providers.destroy');

            Route::post('/bulk-delete', [FleetController::class, 'bulkDestroy'])->name('bulk-delete');
            Route::post('/bulk-provider', [FleetController::class, 'bulkAssignProvider'])->name('bulk-provider');
        });

    // Everything else that mutates the master data managed alongside fleet.
    Route::middleware('permission:fleet.manage')->group(function () {
        Route::prefix('matches')->name('matches.')->group(function () {
            Route::post('/', [MatchesController::class, 'store'])->name('store');
            Route::put('/{id}', [MatchesController::class, 'update'])->name('update');
            Route::delete('/{id}', [MatchesController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('contacts')->name('contacts.')->group(function () {
            Route::post('/', [LmsController::class, 'storeContact'])->name('store');
            Route::put('/{id}', [LmsController::class, 'updateContact'])->name('update');
            Route::delete('/{id}', [LmsController::class, 'destroyContact'])->name('destroy');
        });

        Route::prefix('base-camp-hotels')->name('base-camp-hotels.')->group(function () {
            Route::post('/', [BaseCampHotelController::class, 'store'])->name('store');
            Route::put('/{id}', [BaseCampHotelController::class, 'update'])->name('update');
            Route::delete('/{id}', [BaseCampHotelController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('airports')->name('airports.')->group(function () {
            Route::post('/', [AirportController::class, 'store'])->name('store');
            Route::put('/{airport}', [AirportController::class, 'update'])->name('update');
            Route::delete('/{airport}', [AirportController::class, 'destroy'])->name('destroy');
        });
    });
});
