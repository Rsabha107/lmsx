<?php

/**
 * Venues Routes
 * All routes related to venue management
 */

use App\Http\Controllers\VenuesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:console.view'])->prefix('venues')->name('venues.')->group(function () {
    Route::get('/',      [VenuesController::class, 'index'])->name('index');
    Route::post('/',     [VenuesController::class, 'store'])->name('store');
    Route::put('/{id}',  [VenuesController::class, 'update'])->name('update');
    Route::delete('/{id}', [VenuesController::class, 'destroy'])->name('destroy');
});
