<?php

use App\Http\Controllers\EventsController;
use App\Http\Controllers\EventTeamsController;
use App\Http\Controllers\MatchImportController;
use App\Http\Controllers\TeamFlightsController;
use App\Http\Controllers\TeamImportController;
use App\Http\Controllers\TeamProfileController;
use App\Http\Controllers\TeamStaysController;
use App\Http\Controllers\TeamTrainingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('events')->name('events.')->group(function () {
    Route::get('/', [EventsController::class, 'index'])
        ->middleware('permission:events.view')
        ->name('index');

    // Everything below mutates event-owned data.
    Route::middleware('permission:events.manage')->group(function () {
        Route::post('/',                             [EventsController::class, 'store'])->name('store');
        Route::middleware('event.access:id')->group(function () {
            Route::put('/{id}',                          [EventsController::class, 'update'])->name('update');
            Route::delete('/{id}',                       [EventsController::class, 'destroy'])->name('destroy');

            // Teams (event-owned)
            Route::post('/{id}/teams',                   [EventTeamsController::class, 'store'])->name('teams.store');
            Route::put('/{id}/teams/{teamCode}',         [EventTeamsController::class, 'update'])->name('teams.update');
            Route::delete('/{id}/teams/{teamCode}',      [EventTeamsController::class, 'destroy'])->name('teams.destroy');
            Route::post('/{id}/teams/import',            [TeamImportController::class, 'import'])->name('teams.import');
            // The Add / Edit team dialog: team, arrival, departure, hotel and trainings in one save.
            Route::post('/{id}/teams/profile',           [TeamProfileController::class, 'store'])->name('teams.profile.store');
            Route::put('/{id}/teams/{teamCode}/profile', [TeamProfileController::class, 'update'])->name('teams.profile.update');
            // The same dialog opened from Planning's Edit movement (loads the team and the pick lists).
            Route::get('/{id}/teams/{teamId}/profile',   [TeamProfileController::class, 'show'])->whereNumber('teamId')->name('teams.profile.show');

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
            Route::post('/{id}/flights/sync-bulk',           [TeamFlightsController::class, 'syncBulk'])->middleware('throttle:6,1')->name('flights.sync-bulk');

            // Team stays
            Route::post('/{id}/teams/{teamCode}/stays',      [TeamStaysController::class, 'store'])->name('stays.store');
            Route::put('/{id}/stays/{stayId}',               [TeamStaysController::class, 'update'])->name('stays.update');
            Route::delete('/{id}/stays/{stayId}',            [TeamStaysController::class, 'destroy'])->name('stays.destroy');

            // Team trainings
            Route::post('/{id}/teams/{teamCode}/trainings',  [TeamTrainingsController::class, 'store'])->name('trainings.store');
            Route::put('/{id}/trainings/{trainingId}',       [TeamTrainingsController::class, 'update'])->name('trainings.update');
            Route::delete('/{id}/trainings/{trainingId}',    [TeamTrainingsController::class, 'destroy'])->name('trainings.destroy');
        });
    });
});
