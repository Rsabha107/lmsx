<?php

use App\Http\Controllers\EventsController;
use App\Http\Controllers\TeamFlightsController;
use App\Http\Controllers\TeamStaysController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('events')->name('events.')->group(function () {
    Route::get('/',                              [EventsController::class, 'index'])->name('index');
    Route::post('/',                             [EventsController::class, 'store'])->name('store');
    Route::put('/{id}',                          [EventsController::class, 'update'])->name('update');
    Route::delete('/{id}',                       [EventsController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/teams',                   [EventsController::class, 'assignTeam'])->name('assign-team');
    Route::delete('/{id}/teams/{teamCode}',      [EventsController::class, 'removeTeam'])->name('remove-team');

    // Event venues
    Route::post('/{id}/venues',                  [EventsController::class, 'assignVenue'])->name('assign-venue');
    Route::delete('/{id}/venues/{venueId}',      [EventsController::class, 'removeVenue'])->name('remove-venue');

    // Team flights
    Route::post('/{id}/teams/{teamCode}/flights',    [TeamFlightsController::class, 'store'])->name('flights.store');
    Route::put('/{id}/flights/{flightId}',           [TeamFlightsController::class, 'update'])->name('flights.update');
    Route::delete('/{id}/flights/{flightId}',        [TeamFlightsController::class, 'destroy'])->name('flights.destroy');
    Route::post('/{id}/flights/{flightId}/sync',     [TeamFlightsController::class, 'sync'])->name('flights.sync');

    // Team stays
    Route::post('/{id}/teams/{teamCode}/stays',      [TeamStaysController::class, 'store'])->name('stays.store');
    Route::put('/{id}/stays/{stayId}',               [TeamStaysController::class, 'update'])->name('stays.update');
    Route::delete('/{id}/stays/{stayId}',            [TeamStaysController::class, 'destroy'])->name('stays.destroy');
});
