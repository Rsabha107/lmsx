<?php

/**
 * Mobile API routes (prefix: /api/mobile, see bootstrap/app.php).
 *
 * Token-authenticated via Sanctum for the Flutter supervisor app. Kept separate
 * from the session-authenticated api/* routes declared in routes/web/.
 */

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MobileFeedController;
use App\Http\Controllers\Api\MobileJobController;
use Illuminate\Support\Facades\Route;

Route::name('api.mobile.')->group(function () {
    // Credential stuffing is the main exposure on a public mobile endpoint.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/jobs', [MobileJobController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{job}', [MobileJobController::class, 'show'])->name('jobs.show');
        Route::post(
            '/jobs/{job}/checkpoints/{checkpoint}/complete',
            [MobileJobController::class, 'completeCheckpoint']
        )->name('checkpoints.complete');

        Route::post('/jobs/{job}/issues', [MobileJobController::class, 'reportIssue'])
            ->name('jobs.issues.store');

        Route::get('/checkpoints/{checkpoint}/photo', [MobileJobController::class, 'photo'])
            ->name('checkpoints.photo');
        Route::get('/checkpoints/{checkpoint}/signature', [MobileJobController::class, 'signature'])
            ->name('checkpoints.signature');

        Route::get('/events', [MobileFeedController::class, 'events'])->name('events');
        Route::get('/alerts', [MobileFeedController::class, 'alerts'])->name('alerts');
        Route::get('/map', [MobileFeedController::class, 'map'])->name('map');
        Route::get('/profile', [MobileFeedController::class, 'profile'])->name('profile');
    });
});
