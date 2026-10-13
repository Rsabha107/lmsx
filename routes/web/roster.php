<?php

use App\Http\Controllers\DutyRosterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('duty-roster')->name('duty-roster.')->group(function () {
    Route::middleware('permission:console.view')->group(function () {
        Route::get('/', [DutyRosterController::class, 'index'])->name('index');
        Route::get('/export', [DutyRosterController::class, 'export'])->name('export');
    });

    Route::middleware('permission:roster.manage')->group(function () {
        Route::put('/shifts', [DutyRosterController::class, 'assign'])->name('assign');
        Route::post('/members', [DutyRosterController::class, 'addMembers'])->name('members.store');
        Route::patch('/members/{user}', [DutyRosterController::class, 'updateMember'])->name('members.update');
        Route::delete('/members/{user}', [DutyRosterController::class, 'removeMember'])->name('members.destroy');
        Route::post('/shift-types', [DutyRosterController::class, 'storeShiftType'])->name('shift-types.store');
        Route::put('/shift-types/{shiftType}', [DutyRosterController::class, 'updateShiftType'])->name('shift-types.update');
        Route::delete('/shift-types/{shiftType}', [DutyRosterController::class, 'destroyShiftType'])->name('shift-types.destroy');
    });
});
