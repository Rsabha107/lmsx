<?php

/**
 * Core Application Routes
 * Dashboard, schedule, library, and general app features
 */

use App\Http\Controllers\LmsController;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    // Root is the console dashboard, but mobile-only users are redirected to
    // their workflow rather than bounced with a 403.
    Route::get('/', [LmsController::class, 'dashboard'])->name('dashboard');

    // Core Views
    Route::middleware('permission:console.view')->group(function () {
        Route::get('/schedule', [LmsController::class, 'schedule'])->name('schedule');
        // Route::get('/plans', [LmsController::class, 'plans'])->name('plans'); // Handled by EXAMPLE_ROUTES.php -> PlanManagementController
        Route::get('/library', [LmsController::class, 'library'])->name('library');

        // Communication & Monitoring
        Route::get('/notifications', [LmsController::class, 'notifications'])->name('notifications');
        Route::get('/email', [LmsController::class, 'email'])->name('email');
        Route::post('/email/send', [LmsController::class, 'sendEmail'])->name('email.send');
    });

    Route::get('/audit', [LmsController::class, 'audit'])
        ->middleware('permission:audit.view')
        ->name('audit');
    Route::get('/audit', [LmsController::class, 'audit'])
        ->middleware('permission:audit.view')
        ->name('audit');

    // Session: active event selector
    Route::post('/session/active-event', function (Request $request) {
        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        $eventId = $validated['event_id'] ?? null;
        $user = $request->user();

        if ($eventId && ! $user->canAccessEvent((int) $eventId)) {
            abort(403, 'You do not have access to that event.');
        }

        $request->session()->put('active_event_id', $eventId);

        // active_flag is global: it is the default event every mobile client
        // resolves to, so switching it is not a per-user preference.
        if ($eventId && $user->can('events.set-mobile-default')) {
            Event::whereKeyNot($eventId)->update(['active_flag' => false]);
            Event::whereKey($eventId)->update(['active_flag' => true]);
        }

        return redirect()->back();
    })->name('session.active-event');

    // Session: active plan selector
    Route::post('/session/active-plan', function (Request $request) {
        $request->session()->put('active_plan_id', $request->input('plan_id'));
        return response()->json(['success' => true]);
    })->name('session.active-plan');
});
