<?php

/**
 * Core Application Routes
 * Dashboard, schedule, library, and general app features
 */

use App\Http\Controllers\LmsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    
    // Core Views
    Route::get('/', [LmsController::class, 'dashboard'])->name('dashboard');
    Route::get('/schedule', [LmsController::class, 'schedule'])->name('schedule');
    // Route::get('/plans', [LmsController::class, 'plans'])->name('plans'); // Handled by EXAMPLE_ROUTES.php -> PlanManagementController
    Route::get('/library', [LmsController::class, 'library'])->name('library');
    
    // Communication & Monitoring
    Route::get('/notifications', [LmsController::class, 'notifications'])->name('notifications');
    Route::get('/email', [LmsController::class, 'email'])->name('email');
    Route::get('/audit', [LmsController::class, 'audit'])->name('audit');

    // Session: active event selector
    Route::post('/session/active-event', function (Request $request) {
        Log::info('Setting active event in session', ['event_id' => $request->input('event_id')]);  
        $request->session()->put('active_event_id', $request->input('event_id'));
        return redirect()->back();
    })->name('session.active-event');

    // Session: active plan selector
    Route::post('/session/active-plan', function (Request $request) {
        $request->session()->put('active_plan_id', $request->input('plan_id'));
        return response()->json(['success' => true]);
    })->name('session.active-plan');
});
