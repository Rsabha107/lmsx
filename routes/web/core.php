<?php

/**
 * Core Application Routes
 * Dashboard, schedule, library, and general app features
 */

use App\Http\Controllers\AuditController;
use App\Http\Controllers\LmsController;
use App\Http\Controllers\MobileAppDownloadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::middleware('auth')->group(function () {

    // Root is the console dashboard, but mobile-only users are redirected to
    // their workflow rather than bounced with a 403.
    Route::get('/', [LmsController::class, 'dashboard'])->name('dashboard');

    Route::get('/downloads/mobile-app', MobileAppDownloadController::class)
        ->middleware('role:admin|agency|ground_control|transport|mobile_viewer')
        ->name('downloads.mobile-app');

    Route::inertia('/get-app', 'GetApp')
        ->middleware('role:ground_control|transport|mobile_viewer')
        ->name('get-app');

    // Core Views
    Route::middleware('permission:console.view')->group(function () {
        // The old Schedule page now lives in the Day Board.
        Route::redirect('/schedule', '/day-board?view=schedule');
        // Route::get('/plans', [LmsController::class, 'plans'])->name('plans'); // Handled by EXAMPLE_ROUTES.php -> PlanManagementController
        Route::get('/library', [LmsController::class, 'library'])->name('library');

        // Communication & Monitoring
        Route::get('/notifications', [LmsController::class, 'notifications'])->name('notifications');
        Route::get('/email', [LmsController::class, 'email'])->name('email');
        Route::post('/email/send', [LmsController::class, 'sendEmail'])->name('email.send');
    });

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit');
        Route::get('/audit/export', [AuditController::class, 'export'])->name('audit.export');
    });

    // Session: active event selector
    Route::post('/session/active-event', function (Request $request) {
        $validated = $request->validate([
            // Cancelled events are hidden from the switcher, so don't accept them either.
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->where('active_flag', true)->whereNull('deleted_at')],
        ]);

        $eventId = $validated['event_id'] ?? null;
        $user = $request->user();

        if ($eventId && ! $user->canAccessEvent((int) $eventId)) {
            abort(403, 'You do not have access to that event.');
        }

        $request->session()->put('active_event_id', $eventId);

        return redirect()->back();
    })->name('session.active-event');

    // Session: active plan selector
    Route::post('/session/active-plan', function (Request $request) {
        $validated = $request->validate(['plan_id' => ['nullable', 'integer']]);

        $request->session()->put('active_plan_id', $validated['plan_id'] ?? null);
        return response()->json(['success' => true]);
    })->name('session.active-plan');
});
