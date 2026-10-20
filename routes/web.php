<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BulkMessageController;
use App\Http\Controllers\Api\ActivePackageController;
use App\Http\Controllers\Api\InvoiceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group.
|
*/

// Home
Route::get('/', function () {
    return view('welcome');
});

// API Documentation
Route::get('/api-docs', function () {
    return view('api-docs');
});
Route::get('/api-docs/auto-ai', function () {
    return view('auto-ai-docs');
});
Route::get('/api-docs/wallet', function () {
    return view('wallet-docs');
});
Route::get('/api-docs/catalog', function () {
    return view('catalog-docs');
});

// Legal & Compliance Pages
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');

Route::get('/terms-and-conditions', function () {
    return view('terms-and-conditions');
})->name('terms.conditions');

Route::get('/delete-account', function () {
    return view('delete-account');
})->name('account.deletion');

// WhatsApp Webhook Fallback
Route::match(['GET', 'POST'], '/whatsapp/webhook', [BulkMessageController::class, 'handleWebhook']);
Route::match(['GET', 'POST'], '/whatsapp/webhook/{user_id}', [BulkMessageController::class, 'handleWebhookUserWise']);

// Plan Payment Callback & Checkout
Route::match(['get', 'post'], '/payment/callback', [ActivePackageController::class, 'handlePaymentCallback']);
Route::get('/payment/checkout/{receipt}', [ActivePackageController::class, 'renderCheckoutPage']);

// Public Invoice View & Download Routes
Route::get('/invoices/{receipt}/view', [InvoiceController::class, 'publicView']);
Route::get('/invoices/{receipt}/download', [InvoiceController::class, 'publicDownload']);

// WhatsApp Redirect Shortcut
Route::get('/r/{user_id?}', function ($user_id = null) {
    $number = null;

    if ($user_id !== null && $user_id !== '') {
        $rawId = preg_replace('/\D+/', '', (string) $user_id);
        $user = $rawId !== '' ? \App\Models\User::find($rawId) : null;
        if ($user) {
            $number = $user->companyMobile ?: $user->number;
        }
    }

    if (!$number) {
        $number = env('WHATSAPP_CONTACT_US_NUMBER') ?: '0';
    }

    $number = preg_replace('/\D+/', '', (string) $number) ?: '0';
    return redirect()->away('https://wa.me/' . $number);
});
