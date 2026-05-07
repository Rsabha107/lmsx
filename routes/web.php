<?php

/**
 * Main Web Routes
 * 
 * This file contains authentication routes and includes domain-specific route files.
 * Routes are organized by domain context for better maintainability:
 * 
 * - web/core.php      - Dashboard, schedule, library, notifications
 * - web/jobs.php      - Job operations, checkpoints, mobile views
 * - web/fleet.php     - Fleet, teams, contacts management
 * - web/analytics.php - Analytics and reporting
 * - web/admin.php     - User, role, permission management
 * - EXAMPLE_ROUTES.php - Plan and template management (legacy)
 */

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Guest-only auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Domain-Specific Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/web/core.php';
require __DIR__.'/web/jobs.php';
require __DIR__.'/web/fleet.php';
require __DIR__.'/web/analytics.php';
require __DIR__.'/web/admin.php';

// Legacy routes (to be refactored)
require __DIR__.'/EXAMPLE_ROUTES.php';