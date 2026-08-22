<?php

use App\Http\Controllers\EventsController;
use App\Http\Controllers\EventTeamsController;
use App\Http\Controllers\MatchImportController;
use App\Http\Controllers\TeamFlightsController;
use App\Http\Controllers\TeamImportController;
use App\Http\Controllers\TeamStaysController;
use App\Http\Controllers\TeamTrainingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('events')->name('events.')->group(function () {
    Route::get('/',                              [EventsController::class, 'index'])->name('index');
    Route::post('/',                             [EventsController::class, 'store'])->name('store');
    Route::put('/{id}',                          [EventsController::class, 'update'])->name('update');
    Route::delete('/{id}',                       [EventsController::class, 'destroy'])->name('destroy');

    // Teams (event-owned)
    Route::post('/{id}/teams',                   [EventTeamsController::class, 'store'])->name('teams.store');
    Route::put('/{id}/teams/{teamCode}',         [EventTeamsController::class, 'update'])->name('teams.update');
    Route::delete('/{id}/teams/{teamCode}',      [EventTeamsController::class, 'destroy'])->name('teams.destroy');
    Route::post('/{id}/teams/import',            [TeamImportController::class, 'import'])->name('teams.import');

    // Matches (event-owned)
    Route::post('/{id}/matches/import',          [MatchImportController::class, 'import'])->name('matches.import');

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

    // Team trainings
    Route::post('/{id}/teams/{teamCode}/trainings',  [TeamTrainingsController::class, 'store'])->name('trainings.store');
    Route::put('/{id}/trainings/{trainingId}',       [TeamTrainingsController::class, 'update'])->name('trainings.update');
    Route::delete('/{id}/trainings/{trainingId}',    [TeamTrainingsController::class, 'destroy'])->name('trainings.destroy');
});
