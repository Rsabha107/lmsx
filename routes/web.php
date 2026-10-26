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
 * - web/ai.php        - AI Operations Copilot
 * - web/utilities.php - Standalone data tools (file conversion)
 * - EXAMPLE_ROUTES.php - Plan and template management (legacy)
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MicrosoftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Guest-only auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:web-login')->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:web-password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::get('/auth/microsoft/redirect', [MicrosoftController::class, 'redirectToMicrosoft'])->name('auth.microsoft');
});

// Not guest-only: the phone's browser may hold a console session, which the callback replaces or leaves alone.
Route::get('/auth/microsoft/mobile', [MicrosoftController::class, 'redirectFromMobile'])
    ->middleware('throttle:10,1')
    ->name('auth.microsoft.mobile');
Route::get('/auth/microsoft/callback', [MicrosoftController::class, 'handleMicrosoftCallback'])->name('auth.microsoft.callback');

// Signed in with an admin-set temporary password: EnsurePasswordChanged sends them here first.
Route::middleware('auth')->group(function () {
    Route::get('/password/change', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('/password/change', [ChangePasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.change.update');
});

// Invitations: the short link from the email resolves to a signed, expiring page where the invitee sets a password.
Route::middleware(['guest', 'throttle:30,1'])->group(function () {
    Route::get('/i/{code}', [InvitationController::class, 'resolve'])->name('invitation.short');
    Route::get('/invitation/accept', [InvitationController::class, 'show'])->middleware('signed')->name('invitation.accept');
    Route::post('/invitation/accept', [InvitationController::class, 'store'])->middleware(['signed', 'throttle:6,1'])->name('invitation.store');
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
require __DIR__.'/web/events.php';
require __DIR__.'/web/venues.php';
require __DIR__.'/web/analytics.php';
require __DIR__.'/web/admin.php';
require __DIR__.'/web/plans.php';
require __DIR__.'/web/ai.php';
require __DIR__.'/web/utilities.php';
require __DIR__.'/web/roster.php';
require __DIR__.'/web/recycle.php';

// Legacy routes (templates and admin - to be refactored)
require __DIR__.'/EXAMPLE_ROUTES.php';
