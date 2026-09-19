<?php

/**
 * Admin & Setup Routes
 * Routes for user management, roles, permissions, and system settings
 */

use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// User, role and permission management are privilege-granting surfaces, so the
// whole Setups area is admin-only rather than merely authenticated.
Route::middleware(['auth', 'role:admin'])->prefix('setups')->name('setups.')->group(function () {
    
    // User Management
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
    });
    
    // Role Management
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('/{id}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->name('destroy');
    });
    
    // Permission Management
    Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
        Route::post('/', [PermissionController::class, 'store'])->name('store');
        Route::put('/{id}', [PermissionController::class, 'update'])->name('update');
        Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('destroy');
    });
    
    // System Settings - Movement Time Offsets
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/global', [SettingsController::class, 'updateGlobal'])->name('update-global');
        Route::post('/event', [SettingsController::class, 'updateEvent'])->name('update-event');
        Route::delete('/{id}', [SettingsController::class, 'destroy'])->name('destroy');
        Route::post('/preview', [SettingsController::class, 'preview'])->name('preview');
        Route::post('/preview-impact', [SettingsController::class, 'previewImpact'])->name('preview-impact');
    });
});
