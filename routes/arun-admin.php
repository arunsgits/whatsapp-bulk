<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminWebController;

/*
|--------------------------------------------------------------------------
| Super Admin Web Control Plane Routes (/arun-admin)
|--------------------------------------------------------------------------
|
| Dedicated routes for the Arun Admin web interface.
| All routes run under web middleware with session state and CSRF protection.
|
*/

Route::prefix('arun-admin')->group(function () {
    // Authentication & Entry
    Route::get('/', [AdminWebController::class, 'index'])->name('arun-admin.index');
    Route::get('/login', [AdminWebController::class, 'loginForm'])->name('arun-admin.login');
    Route::post('/login', [AdminWebController::class, 'login'])->name('arun-admin.login.submit');
    Route::match(['get', 'post'], '/logout', [AdminWebController::class, 'logout'])->name('arun-admin.logout');

    // Super Admin Dashboard Overview
    Route::get('/dashboard', [AdminWebController::class, 'dashboard'])->name('arun-admin.dashboard');

    // Super Admin Profile & Password Management
    Route::get('/profile', [AdminWebController::class, 'profile'])->name('arun-admin.profile');
    Route::post('/profile', [AdminWebController::class, 'updateProfile'])->name('arun-admin.profile.update');
    Route::post('/profile/change-password', [AdminWebController::class, 'changePassword'])->name('arun-admin.profile.password');

    // Tenant / Client Management Module (under admin/clients/)
    Route::get('/clients', [AdminWebController::class, 'clients'])->name('arun-admin.clients.index');
    Route::post('/clients/create', [AdminWebController::class, 'storeClient'])->name('arun-admin.clients.store');
    Route::get('/clients/{id}', [AdminWebController::class, 'showClient'])->name('arun-admin.clients.show');
    Route::post('/clients/{id}/update', [AdminWebController::class, 'updateClient'])->name('arun-admin.clients.update');
    Route::post('/clients/{id}/update-waba', [AdminWebController::class, 'updateClientWaba'])->name('arun-admin.clients.update-waba');
    Route::post('/clients/{id}/update-ai', [AdminWebController::class, 'updateClientAi'])->name('arun-admin.clients.update-ai');
    Route::post('/clients/{id}/adjust-wallet', [AdminWebController::class, 'adjustClientWallet'])->name('arun-admin.clients.adjust-wallet');
    Route::post('/clients/{id}/toggle-wallet', [AdminWebController::class, 'toggleClientWallet'])->name('arun-admin.clients.toggle-wallet');
    Route::post('/clients/{id}/change-plan', [AdminWebController::class, 'changeClientPlan'])->name('arun-admin.clients.change-plan');
    Route::post('/clients/{id}/toggle-status', [AdminWebController::class, 'toggleClientStatus'])->name('arun-admin.clients.toggle-status');

    // Backward compatibility aliases for /users
    Route::get('/users', [AdminWebController::class, 'clients'])->name('arun-admin.users');
    Route::post('/users/{id}/toggle-status', [AdminWebController::class, 'toggleUserStatus'])->name('arun-admin.users.toggle-status');

    // Financial Transaction History Module (under admin/transactions/)
    Route::get('/transactions', [AdminWebController::class, 'transactions'])->name('arun-admin.transactions.index');

    // Subscription Plan / Package Management
    Route::get('/packages', [AdminWebController::class, 'packages'])->name('arun-admin.packages');
    Route::post('/packages/create', [AdminWebController::class, 'storePackage'])->name('arun-admin.packages.create');
    Route::post('/packages/{id}/update', [AdminWebController::class, 'updatePackage'])->name('arun-admin.packages.update');
    Route::post('/packages/{id}/delete', [AdminWebController::class, 'deletePackage'])->name('arun-admin.packages.delete');

    // Global Platform Settings (Razorpay, SMTP, Batch Engine, Pricing Rates)
    Route::get('/settings', [AdminWebController::class, 'settings'])->name('arun-admin.settings');
    Route::post('/settings', [AdminWebController::class, 'updateSettings'])->name('arun-admin.settings.update');

    // System Health & Diagnostics
    Route::get('/health', [AdminWebController::class, 'health'])->name('arun-admin.health');
    Route::get('/system-health', [AdminWebController::class, 'health']);
});
