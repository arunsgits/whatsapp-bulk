<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Admin\AdminWalletController;
use App\Http\Controllers\Admin\AdminModuleController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminEmailTemplateController;
use App\Http\Controllers\Admin\AdminInvoiceTemplateController;
use App\Http\Controllers\Api\DashboardContentController;
use App\Http\Controllers\Api\DashboardImageController;

/*
|--------------------------------------------------------------------------
| Super Admin API Routes
|--------------------------------------------------------------------------
|
| Here is where super admin REST API endpoints are registered.
| All requests and responses use JSON (`application/json`).
|
*/

// Public Admin Auth Routes
Route::post('/auth/login', [AdminAuthController::class, 'login']);

// Protected Admin Routes
Route::middleware(['auth:api'])->group(function () {

    // 1. Auth & Profile Endpoints
    Route::get('/profile', [AdminAuthController::class, 'getProfile']);
    Route::match(['put', 'post'], '/profile', [AdminAuthController::class, 'updateProfile']);
    Route::post('/profile/change-password', [AdminAuthController::class, 'changePassword']);

    // Admin Middleware protected endpoints
    Route::middleware(['admin'])->group(function () {

        // 2. Dashboard Endpoints
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'getStats']);
        Route::get('/dashboard/expiring', [AdminDashboardController::class, 'getExpiring']);
        Route::get('/dashboard/revenue', [AdminDashboardController::class, 'getRevenue']);

        // 3. User Accounts API Endpoints
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{userId}', [AdminUserController::class, 'show']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::match(['put', 'post'], '/users/{userId}', [AdminUserController::class, 'update']);
        Route::match(['put', 'post'], '/users/{userId}/status', [AdminUserController::class, 'updateStatus']);
        Route::delete('/users/{userId}', [AdminUserController::class, 'destroy']);
        Route::post('/users/{userId}/renew', [AdminUserController::class, 'renew']);
        Route::match(['get', 'post'], '/users/{userId}/renewal-payment-link', [AdminUserController::class, 'generateRenewalPaymentLink']);
        Route::post('/users/{userId}/rotate-api-key', [AdminUserController::class, 'rotateApiKey']);
        Route::match(['get', 'put', 'post'], '/users/{userId}/permissions', [AdminUserController::class, 'manageUserPermissions']);

        // Dedicated User Package Plan Management APIs
        Route::get('/users/{userId}/plan', [AdminUserController::class, 'getUserPlan']);
        Route::get('/users/{userId}/plan-history', [AdminUserController::class, 'getUserPlanHistory']);
        Route::match(['put', 'post'], '/users/{userId}/plan', [AdminUserController::class, 'updateUserPlan']);
        Route::post('/users/{userId}/upgrade-plan', [AdminUserController::class, 'upgradeUserPlan']);
        Route::post('/users/{userId}/custom-plan', [AdminUserController::class, 'assignCustomPlan']);

        // Dedicated User Meta/WABA Credentials & Settings Setup APIs
        Route::get('/users/{userId}/settings', [AdminUserController::class, 'getUserSettings']);
        Route::match(['put', 'post'], '/users/{userId}/settings', [AdminUserController::class, 'updateUserSettings']);
        Route::post('/users/{userId}/subscribe-waba', [AdminUserController::class, 'subscribeUserWaba']);
        Route::post('/users/{userId}/register-phone', [AdminUserController::class, 'registerUserPhoneNumber']);
        Route::get('/users/{userId}/phone-status', [AdminUserController::class, 'getUserPhoneStatus']);

        Route::get('/users/{userId}/wallet', [AdminUserController::class, 'getUserWallet']);
        Route::match(['put', 'post'], '/users/{userId}/wallet', [AdminUserController::class, 'updateUserWallet']);
        Route::post('/users/{userId}/wallet/credit', [AdminUserController::class, 'creditUserWallet']);

        // User Reminders API Endpoints (Payment, Renewal, Wallet Recharge)
        Route::post('/users/{userId}/send-reminder', [AdminUserController::class, 'sendReminder']);
        Route::post('/users/{userId}/reminder/renewal', [AdminUserController::class, 'sendRenewalReminder']);
        Route::post('/users/{userId}/reminder/wallet', [AdminUserController::class, 'sendWalletReminder']);
        Route::post('/users/{userId}/reminder/payment', [AdminUserController::class, 'sendPaymentReminder']);

        // 4. Modules API Endpoints
        Route::get('/modules', [AdminModuleController::class, 'index']);
        Route::get('/modules/{id}', [AdminModuleController::class, 'show']);
        Route::post('/modules', [AdminModuleController::class, 'store']);
        Route::match(['put', 'post'], '/modules/{id}', [AdminModuleController::class, 'update']);
        Route::delete('/modules/{id}', [AdminModuleController::class, 'destroy']);

        // Wallet transactions & overview (all users)
        Route::get('/wallet/transactions', [AdminWalletController::class, 'transactions']);
        Route::get('/wallet/summary', [AdminWalletController::class, 'summary']);
        Route::get('/wallet/users', [AdminWalletController::class, 'users']);
        Route::get('/wallet/manual-history', [AdminWalletController::class, 'manualHistory']);
        Route::post('/wallet/credit', [AdminWalletController::class, 'credit']);
        Route::get('/wallet/users/{userId}', [AdminWalletController::class, 'userWallet']);
        Route::post('/wallet/users/{userId}/credit', [AdminWalletController::class, 'credit']);
        Route::match(['put', 'post'], '/wallet/users/{userId}/toggle', [AdminWalletController::class, 'toggle']);

        // 5. Subscription Plans API Endpoints (Monthly & Yearly Plan Management)
        Route::get('/plans/summary', [AdminPlanController::class, 'summary']);
        Route::get('/plans/gst', [AdminPlanController::class, 'getGstSettings']);
        Route::match(['put', 'post'], '/plans/gst', [AdminPlanController::class, 'updateGstSettings']);
        Route::get('/plans', [AdminPlanController::class, 'index']);
        Route::get('/plans/{planId}', [AdminPlanController::class, 'show']);
        Route::post('/plans', [AdminPlanController::class, 'store']);
        Route::match(['put', 'post'], '/plans/{planId}', [AdminPlanController::class, 'update']);
        Route::post('/plans/{planId}/duplicate', [AdminPlanController::class, 'duplicate']);
        Route::post('/plans/{planId}/toggle-cycle', [AdminPlanController::class, 'toggleCycle']);
        Route::delete('/plans/{planId}', [AdminPlanController::class, 'destroy']);
        Route::get('/plans/transactions', [AdminPlanController::class, 'transactions']);
        Route::post('/plans/upgrade', [AdminPlanController::class, 'upgrade']);

        // Invoices API Endpoints (Plan and Subscription Invoices)
        Route::get('/invoices', [AdminInvoiceController::class, 'index']);
        Route::get('/invoices/{invoiceId}', [AdminInvoiceController::class, 'show']);
        Route::get('/invoices/{invoiceId}/view', [AdminInvoiceController::class, 'viewHtml']);
        Route::get('/invoices/{invoiceId}/download', [AdminInvoiceController::class, 'download']);
        Route::get('/users/{userId}/invoices', [AdminInvoiceController::class, 'userInvoices']);
        Route::get('/users/{userId}/invoices/{invoiceId}/download', [AdminInvoiceController::class, 'download']);
        Route::post('/users/{userId}/generate-invoice', [AdminInvoiceController::class, 'generateForUser']);
        Route::post('/invoices/{invoiceId}/send-reminder', [AdminInvoiceController::class, 'sendReminder']);

        // 6. Global Settings & Credentials API Endpoints
        Route::get('/settings/credentials', [AdminSettingsController::class, 'getCredentials']);
        Route::get('/settings/gst', [AdminSettingsController::class, 'getGst']);
        Route::match(['put', 'post'], '/settings/gst', [AdminSettingsController::class, 'updateGst']);
        Route::get('/settings/smtp', [AdminSettingsController::class, 'getSmtp']);
        Route::match(['put', 'post'], '/settings/smtp', [AdminSettingsController::class, 'updateSmtp']);
        Route::post('/settings/smtp/test', [AdminSettingsController::class, 'testSmtp']);
        Route::match(['put', 'post'], '/settings/credentials/sms', [AdminSettingsController::class, 'updateSms']);
        Route::match(['put', 'post'], '/settings/credentials/webhook', [AdminSettingsController::class, 'updateWebhook']);
        Route::post('/settings/credentials/webhook/rotate-secret', [AdminSettingsController::class, 'rotateWebhookSecret']);
        Route::match(['put', 'post'], '/settings/credentials/razorpay', [AdminSettingsController::class, 'updateRazorpay']);
        Route::match(['put', 'post'], '/settings/credentials/wallet-pricing', [AdminSettingsController::class, 'updateWalletPricing']);

        // 7. Staff Management API Endpoints
        Route::get('/staff', [AdminStaffController::class, 'index']);
        Route::get('/staff/permissions', [AdminStaffController::class, 'getAvailablePermissions']);
        Route::get('/staff/{id}', [AdminStaffController::class, 'show']);
        Route::post('/staff', [AdminStaffController::class, 'store']);
        Route::match(['put', 'post'], '/staff/{id}', [AdminStaffController::class, 'update']);
        Route::match(['put', 'post'], '/staff/{id}/status', [AdminStaffController::class, 'updateStatus']);
        Route::delete('/staff/{id}', [AdminStaffController::class, 'destroy']);

        // 8. Email Templates Management API Endpoints
        Route::get('/email-templates/placeholders', [AdminEmailTemplateController::class, 'getPlaceholders']);
        Route::post('/email-templates/upload-image', [AdminEmailTemplateController::class, 'uploadImage']);
        Route::post('/email-templates/preview', [AdminEmailTemplateController::class, 'preview']);
        Route::get('/email-templates', [AdminEmailTemplateController::class, 'index']);
        Route::get('/email-templates/{id}', [AdminEmailTemplateController::class, 'show']);
        Route::post('/email-templates', [AdminEmailTemplateController::class, 'store']);
        Route::match(['put', 'post'], '/email-templates/{id}', [AdminEmailTemplateController::class, 'update']);
        Route::post('/email-templates/{id}/toggle-status', [AdminEmailTemplateController::class, 'toggleStatus']);
        Route::match(['get', 'post'], '/email-templates/{id}/preview', [AdminEmailTemplateController::class, 'preview']);
        Route::post('/email-templates/{id}/test-send', [AdminEmailTemplateController::class, 'testSend']);
        Route::delete('/email-templates/{id}', [AdminEmailTemplateController::class, 'destroy']);

        // 9. Dynamic Invoice Templates API Endpoints
        Route::get('/invoice-templates/placeholders', [AdminInvoiceTemplateController::class, 'getPlaceholders']);
        Route::post('/invoice-templates/upload-logo', [AdminInvoiceTemplateController::class, 'uploadLogo']);
        Route::get('/invoice-templates', [AdminInvoiceTemplateController::class, 'index']);
        Route::get('/invoice-templates/{id}', [AdminInvoiceTemplateController::class, 'show']);
        Route::post('/invoice-templates', [AdminInvoiceTemplateController::class, 'store']);
        Route::match(['put', 'post'], '/invoice-templates/{id}', [AdminInvoiceTemplateController::class, 'update']);
        Route::post('/invoice-templates/{id}/toggle-status', [AdminInvoiceTemplateController::class, 'toggleStatus']);
        Route::match(['get', 'post'], '/invoice-templates/{id}/preview', [AdminInvoiceTemplateController::class, 'preview']);
        Route::post('/invoice-templates/{id}/reset-default', [AdminInvoiceTemplateController::class, 'resetDefault']);
        Route::delete('/invoice-templates/{id}', [AdminInvoiceTemplateController::class, 'destroy']);

        // 10. Dashboard Content & Image Management API Endpoints
        Route::post('/dashboard-content', [DashboardContentController::class, 'store']);
        Route::delete('/dashboard-content/{id?}', [DashboardContentController::class, 'destroy']);

        Route::post('/dashboard-image', [DashboardImageController::class, 'store']);
        Route::delete('/dashboard-image/{id?}', [DashboardImageController::class, 'destroy']);
    });
});


