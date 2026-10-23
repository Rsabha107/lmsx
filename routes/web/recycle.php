<?php

use App\Http\Controllers\RecycleBinController;
use Illuminate\Support\Facades\Route;

// Setups → Recycle bin: approve or reject delete requests, restore deleted batches. Admins only.
Route::middleware(['auth', 'permission:plans.manage'])->group(function () {
    Route::get('/setups/recycle-bin', [RecycleBinController::class, 'index'])->name('recycle-bin');

    Route::prefix('recycle-bin')->name('recycle-bin.')->group(function () {
        Route::post('/{deletion}/approve', [RecycleBinController::class, 'approve'])->name('approve');
        Route::post('/{deletion}/reject', [RecycleBinController::class, 'reject'])->name('reject');
        Route::post('/{deletion}/withdraw', [RecycleBinController::class, 'withdraw'])->name('withdraw');
        Route::post('/{deletion}/restore', [RecycleBinController::class, 'restore'])->name('restore');
        Route::post('/earlier-plans/{plan}/restore', [RecycleBinController::class, 'restoreEarlierPlan'])->name('earlier-plan.restore');
    });
});
