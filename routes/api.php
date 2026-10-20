<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\BulkMessageController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\ActivePackageController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\WhatsAppInboxController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Api\AppLiveController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\DashboardContentController;
use App\Http\Controllers\Api\DashboardImageController;
use App\Http\Controllers\Api\WhatsAppAccountController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::get('/', function () {
    return response()->json(['message' => 'Backend is running 🚀']);
});

// Legal & Compliance APIs (Privacy Policy, Terms & Conditions, Delete Account)
Route::get('/privacy-policy', [LegalController::class, 'getPrivacyPolicy']);
Route::get('/terms-and-conditions', [LegalController::class, 'getTermsAndConditions']);
Route::get('/delete-account', [LegalController::class, 'getDeleteAccountInfo']);
Route::post('/delete-account-request', [LegalController::class, 'submitDeleteAccountRequest']);

// App Live APIs (1: Get Value, 2: Toggle On/Off)
Route::get('/is-app-live', [AppLiveController::class, 'getAppLive']);
Route::post('/is-app-live/toggle', [AppLiveController::class, 'toggleAppLive']);

// Public Plans Listing
Route::get('/plans', [PackageController::class, 'index']);

// Dynamic Dashboard Content & Images (Public GET, Protected POST & DELETE)
Route::get('/dashboard-content', [DashboardContentController::class, 'index']);
Route::get('/dashboard-image', [DashboardImageController::class, 'index']);

$redirectWhatsappHandler = function ($user_id = null) {
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
    $url = 'https://wa.me/' . $number;

    return redirect()->away($url);
};

Route::get('/r/{user_id?}', $redirectWhatsappHandler);
// Cache management routes (public for development)
Route::get('/clear-cache', function () {
    try {
        $cleared = [];

        // Clear application cache
        \Artisan::call('cache:clear');
        $cleared['application_cache'] = 'Application cache cleared';

        // Clear route cache
        \Artisan::call('route:clear');
        $cleared['route_cache'] = 'Route cache cleared';

        // Clear config cache
        \Artisan::call('config:clear');
        $cleared['config_cache'] = 'Config cache cleared';

        // Clear view cache only if views directory exists
        if (is_dir(resource_path('views'))) {
            \Artisan::call('view:clear');
            $cleared['view_cache'] = 'View cache cleared';
        } else {
            $cleared['view_cache'] = 'Views directory not found, skipping view cache clear';
        }

        // Clear optimization cache
        \Artisan::call('optimize:clear');
        $cleared['optimization_cache'] = 'Optimization cache cleared';

        return response()->json([
            'status' => true,
            'message' => 'All caches cleared successfully',
            'cleared' => $cleared
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Error clearing caches: ' . $e->getMessage()
        ], 500);
    }
});

// Simple migrate route (use cautiously; consider protecting in production)
Route::get('/migrate', function () {
    try {
        $output = \Artisan::call('migrate', ['--force' => true]);
        return response()->json([
            'status' => true,
            'message' => 'Migrations executed successfully',
            'artisan_output' => \Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Migration failed: ' . $e->getMessage(),
        ], 500);
    }
});

// Simple passport:install route (use cautiously; consider protecting in production)
Route::get('/passport-install', function () {
    try {
        $output = \Artisan::call('passport:install', ['--force' => true]);
        return response()->json([
            'status' => true,
            'message' => 'Passport installed successfully',
            'artisan_output' => \Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Passport installation failed: ' . $e->getMessage(),
        ], 500);
    }
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
 
// WhatsApp webhook - global and user-wise endpoints
Route::match(['GET', 'POST'], '/whatsapp/webhook', [BulkMessageController::class, 'handleWebhook']);
Route::match(['GET', 'POST'], '/whatsapp/webhook/{user_id}', [BulkMessageController::class, 'handleWebhookUserWise']);

// Signed conversation media (no auth required; valid signature allows direct link / <img> access)
Route::get('/conversations/media/signed/{conversationId}/{mediaId}', [ConversationController::class, 'serveSignedMedia'])
    ->name('conversations.media.signed');

// Public media routes (no authentication required)
Route::get('/media/{filename}', function($filename) {
    $filePath = public_path('uploads/media/' . $filename);

    if (!file_exists($filePath)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    $mimeType = mime_content_type($filePath);
    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000' // Cache for 1 year
    ]);
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth & Profile routes
    Route::get('/auth/profile', [AuthController::class, 'getProfile']);
    Route::post('/auth/edit-profile', [AuthController::class, 'editProfile']);
    Route::put('/auth/edit-profile', [AuthController::class, 'editProfile']);
    Route::post('/auth/test-upload', [AuthController::class, 'testFileUpload']);
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Contact & Group routes (contacts module)
    Route::middleware(['staff.permission:contacts'])->group(function () {
        Route::apiResource('contacts', ContactController::class);
        Route::post('/contacts/import', [ContactController::class, 'import']);
        Route::get('/contacts/limits', [ContactController::class, 'getLimits']);
        Route::post('/contacts/delete', [ContactController::class, 'removeContacts']);

        Route::post('/groups', [GroupController::class, 'store']);
        Route::put('/groups/{id}', [GroupController::class, 'update']);
        Route::delete('/groups/{id}', [GroupController::class, 'destroy']);
        Route::get('/groups', [GroupController::class, 'index']);
        Route::get('/groups/{id}', [GroupController::class, 'show']);
        Route::post('/groups/assign', [GroupController::class, 'addContacts']);
        Route::post('/groups/remove', [GroupController::class, 'removeContacts']);

        // WhatsApp Multi-Account Connection & Group Grabber routes
        Route::get('/whatsapp-accounts', [WhatsAppAccountController::class, 'index']);
        Route::post('/whatsapp-accounts/connect', [WhatsAppAccountController::class, 'connect']);
        Route::get('/whatsapp-accounts/{id}/status', [WhatsAppAccountController::class, 'status']);
        Route::post('/whatsapp-accounts/{id}/disconnect', [WhatsAppAccountController::class, 'disconnect']);
        Route::delete('/whatsapp-accounts/{id}', [WhatsAppAccountController::class, 'destroy']);
        Route::get('/whatsapp-accounts/{id}/groups', [WhatsAppAccountController::class, 'getGroups']);
        Route::post('/whatsapp-accounts/{id}/groups/export', [WhatsAppAccountController::class, 'exportGroupContacts']);
    });

    // Template routes (templates module)
    Route::middleware(['staff.permission:templates'])->group(function () {
        Route::get('/templates/sync-templates', [TemplateController::class, 'syncTemplates']);
        Route::get('/templates/user', [TemplateController::class, 'getUserTemplates']);
        Route::get('/templates/user/{templateId}', [TemplateController::class, 'getUserTemplateById']);
        Route::get('/templates/filtered', [TemplateController::class, 'getFilteredTemplates']);
        Route::get('/templates/status', [TemplateController::class, 'getTemplatesByStatus']);
        Route::get('/templates/limits', [TemplateController::class, 'getLimits']);
        Route::post('/templates/custom', [TemplateController::class, 'addCustomTemplate']);
        Route::put('/templates/custom/{templateId}', [TemplateController::class, 'editCustomTemplate']);
        Route::delete('/templates/custom/{templateId}', [TemplateController::class, 'deleteCustomTemplate']);
        Route::put('/templates/status/{templateId}', [TemplateController::class, 'updateStatus']);
        Route::post('/template/create', [TemplateController::class, 'createWhatsAppTemplate']);
    });

    // Bulk Message & Campaign routes (campaigns module)
    Route::middleware(['staff.permission:campaigns'])->group(function () {
        Route::apiResource('bulkmessage', BulkMessageController::class);
        Route::post('/bulk-messages/{id}/update', [BulkMessageController::class, 'updateCampaign']);
        Route::get('/bulk-messages/{id}/stats', [BulkMessageController::class, 'getStats']);
        Route::get('/bulk-messages/{id}/delivery-status', [BulkMessageController::class, 'getDeliveryStatus']);
        Route::get('/bulk-messages/{id}/delivery-status/summary', [BulkMessageController::class, 'getDeliveryStatusSummary']);
        Route::get('/bulk-messages/{campaignId}/contact/{contactId}/delivery-status', [BulkMessageController::class, 'getContactDeliveryStatus']);
        Route::get('/bulk-messages/{id}/status', [BulkMessageController::class, 'getCampaignStatus']);
        Route::put('/bulk-messages/{id}/update-status', [BulkMessageController::class, 'updateCampaignStatusManually']);
        Route::post('/bulk-messages/resend/{id}', [BulkMessageController::class, 'resendFailedAndPending']);
        Route::get('/batch-settings', [BulkMessageController::class, 'getBatchSettings']);
        Route::post('/batch-settings', [BulkMessageController::class, 'updateBatchSettings']);
    });

    // Conversation & Message routes (conversations module)
    Route::middleware(['staff.permission:conversations'])->group(function () {
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/stats', [ConversationController::class, 'getStats']);
        Route::get('/conversations/unread', [ConversationController::class, 'getUnreadConversations']);
        Route::get('/conversations/archived', [ConversationController::class, 'getArchivedConversations']);
        Route::get('/conversations/latest/contacts', [ConversationController::class, 'getLatestContacts']);
        Route::get('/conversations/{id}', [ConversationController::class, 'show'])->where('id', '[0-9]+');
        Route::get('/conversations/{conversationId}/media/{mediaId}', [ConversationController::class, 'downloadMedia']);
        Route::match(['post', 'put'], '/conversations/{id}/status', [ConversationController::class, 'updateStatus'])->where('id', '[0-9]+');
        Route::match(['post', 'put'], '/conversations/{id}/tag', [ConversationController::class, 'toggleTag'])->where('id', '[0-9]+');
        Route::match(['post', 'put'], '/conversations/{id}/important', [ConversationController::class, 'setImportant'])->where('id', '[0-9]+');
        Route::match(['post', 'put'], '/conversations/{id}/close', [ConversationController::class, 'closeConversation'])->where('id', '[0-9]+');
        Route::match(['post', 'put'], '/conversations/{id}/archive', [ConversationController::class, 'archiveConversation'])->where('id', '[0-9]+');
        Route::match(['post', 'put'], '/conversations/{id}/reopen', [ConversationController::class, 'reopenConversation'])->where('id', '[0-9]+');
        Route::post('/conversations/{id}/ai-reply', [ConversationController::class, 'setAiReplyPreference'])->where('id', '[0-9]+');

        Route::post('/messages/send', [MessageController::class, 'sendMessage']);
        Route::get('/messages/conversation/{conversationId}', [MessageController::class, 'getMessages']);
        Route::post('/messages/{conversationId}/mark-read', [MessageController::class, 'markAsRead']);
        Route::get('/messages/eligible-conversations', [MessageController::class, 'getEligibleConversations']);
        Route::get('/messages/{conversationId}/check-eligibility', [MessageController::class, 'checkEligibility']);
    });

    // Reports & Dashboard routes (reports module)
    Route::middleware(['staff.permission:reports'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'getDashboard']);
        Route::get('/reports', [ReportsController::class, 'index']);
        Route::get('/campaigns', [ReportsController::class, 'index1']);
        Route::get('/reports/campaign/{id}/delivery-status', [ReportsController::class, 'getCampaignDeliveryStatus']);
        Route::match(['get', 'post'], '/reports/campaign/{id}/check-delivery', [ReportsController::class, 'checkCampaignDelivery']);
    });

    // Settings routes (settings module)
    Route::middleware(['staff.permission:settings'])->group(function () {
        Route::post('/settings/connect-facebook', [SettingsController::class, 'connectFacebook']);
        Route::post('/settings/refresh-token', [SettingsController::class, 'refreshToken']);
        Route::post('/settings/save-config', [SettingsController::class, 'saveSettings']);
        Route::post('/settings/register-phone', [SettingsController::class, 'registerPhoneNumber']);
        Route::get('/settings/phone-status', [SettingsController::class, 'getPhoneStatus']);
        Route::match(['put', 'post'], '/settings/auto-ai', [SettingsController::class, 'updateAutoAi']);
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::match(['put', 'post'], '/settings', [SettingsController::class, 'store']);
    });

    // Knowledge Base / FAQs API routes
    Route::prefix('knowledge-base')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'store']);
        Route::post('/import-url', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'importUrl']);
        Route::get('/{id}', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'show']);
        Route::match(['put', 'patch', 'post'], '/{id}', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'destroy']);
    });

    // WhatsApp Catalog & Commerce routes (catalog module)
    Route::prefix('catalog')->group(function () {
        // Token diagnostics & permission check
        Route::get('/diagnostics', [CatalogController::class, 'diagnostics']);

        // Commerce Settings (Catalog & Cart visibility on phone number)
        Route::get('/settings', [CatalogController::class, 'getSettings']);
        Route::post('/settings', [CatalogController::class, 'updateSettings']);

        // Catalog Discovery & Selection
        Route::get('/catalogs', [CatalogController::class, 'listCatalogs']);
        Route::post('/select', [CatalogController::class, 'selectCatalog']);
        Route::get('/details/{catalog_id?}', [CatalogController::class, 'getCatalogDetails']);
        Route::post('/create', [CatalogController::class, 'createCatalog']);
        Route::get('/categories', [CatalogController::class, 'getCategories']);

        // Product Sets Management (Meta Commerce Manager Product Sets)
        Route::get('/sets', [CatalogController::class, 'getSets']);
        Route::post('/sets', [CatalogController::class, 'createSet']);
        Route::get('/sets/{setId}', [CatalogController::class, 'getSet']);
        Route::get('/sets/{setId}/products', [CatalogController::class, 'getSetProducts']);
        Route::post('/sets/{setId}/products', [CatalogController::class, 'addProductsToSet']);
        Route::match(['delete', 'post'], '/sets/{setId}/products/remove', [CatalogController::class, 'removeProductsFromSet']);
        Route::delete('/sets/{setId}', [CatalogController::class, 'deleteSet']);

        // Products in Catalog (Read & Search)
        Route::get('/products', [CatalogController::class, 'getProducts']);
        Route::get('/products/bulk/template', [CatalogController::class, 'downloadBulkTemplate']);
        Route::get('/products/{identifier}', [CatalogController::class, 'getProduct']);

        // Product Management (Create, Bulk Create, Update, Delete in Meta Catalog)
        Route::post('/products/bulk', [CatalogController::class, 'bulkStoreProducts']);
        Route::post('/products', [CatalogController::class, 'storeProduct']);
        Route::match(['put', 'post'], '/products/{productId}', [CatalogController::class, 'updateProduct']);
        Route::delete('/products/{productId}', [CatalogController::class, 'deleteProduct']);

        // Interactive Catalog Messaging (Storefront, Single Product, Multi-Product)
        Route::post('/send-catalog', [CatalogController::class, 'sendCatalog']);
        Route::post('/send-product', [CatalogController::class, 'sendProduct']);
        Route::post('/send-product-list', [CatalogController::class, 'sendProductList']);
    });

    // Billing, wallet recharge, credits & active package
    Route::middleware(['staff.permission:billing'])->group(function () {
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/history', [WalletController::class, 'history']);
        Route::post('/wallet/create-order', [WalletController::class, 'createOrder']);
        Route::post('/wallet/verify', [WalletController::class, 'verify']);

        Route::post('/credits/create-order', [CreditController::class, 'createOrder']);
        Route::post('/credits/verify', [CreditController::class, 'verifyPayment']);
        Route::post('/credits/deduct', [CreditController::class, 'deductCredits']);
        Route::get('/credits/balance', [CreditController::class, 'getBalance']);
        Route::get('/credits/history', [CreditController::class, 'getHistory']);
        Route::get('/credits/report', [CreditController::class, 'creditReport']);

        // Active Package & Plan routes
        Route::apiResource('active-packages', ActivePackageController::class);
        Route::apiResource('packages', PackageController::class);
        Route::get('/plans', [PackageController::class, 'index']);
        Route::post('/plan/create', [ActivePackageController::class, 'store']);
        Route::post('/plan/assign', [ActivePackageController::class, 'assignPackage']);
        Route::post('/plan/subscribe', [ActivePackageController::class, 'subscribe']);
        Route::post('/plan/upgrade', [ActivePackageController::class, 'upgrade']);
        Route::post('/plan/verify', [ActivePackageController::class, 'verify']);
        Route::match(['get', 'post'], '/plan/renew/{userId?}', [ActivePackageController::class, 'renew']);
        Route::get('/plan/user/{userId?}', [ActivePackageController::class, 'getUserPlan']);
        Route::get('/plan/history/{userId?}', [ActivePackageController::class, 'getUserPlanHistory']);
        Route::get('/plan/renewal-status', [ActivePackageController::class, 'getRenewalStatus']);
        Route::match(['get', 'post'], '/plan/renewal-payment-link', [ActivePackageController::class, 'generateRenewalPaymentLink']);
        Route::post('/plan/verify-renewal', [ActivePackageController::class, 'verifyRenewalPayment']);

        // Invoices (client billing)
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoiceId}', [InvoiceController::class, 'show']);
        Route::get('/invoices/{invoiceId}/view', [InvoiceController::class, 'viewHtml']);
        Route::get('/invoices/{invoiceId}/download', [InvoiceController::class, 'download']);
    });

    // Notification routes
    Route::get('/notification', [NotificationController::class, 'index']);
    Route::post('/notification/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notification', [NotificationController::class, 'store']);
    Route::get('/notification/{id}', [NotificationController::class, 'show']);
    Route::put('/notification/{id}', [NotificationController::class, 'update']);
    Route::delete('/notification/{id}', [NotificationController::class, 'destroy']);
    Route::post('/notification/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // User routes
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users/add', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/edit/{id}', [UserController::class, 'update']);
    Route::delete('/users/delete/{id}', [UserController::class, 'destroy']);
    Route::put('/users/status/{id}', [UserController::class, 'updateStatus']);

    // Media routes (stats before apiResource so "stats" is not treated as an id)
    Route::get('/media/stats', [MediaController::class, 'stats']);
    Route::apiResource('media', MediaController::class);




    // ─── WhatsApp CRM System API Routes ──────────────────────────────────────────
    Route::prefix('whatsapp')->group(function () {
        // Chat Inbox
        Route::get('/inbox', [WhatsAppInboxController::class, 'inbox']);
        Route::get('/inbox/search', [WhatsAppInboxController::class, 'searchInbox']);
        Route::get('/inbox/conversations', [WhatsAppInboxController::class, 'conversationList']);
        Route::get('/inbox/media/{filename}', [WhatsAppInboxController::class, 'media']);
        Route::get('/inbox/meta-media/{message}', [WhatsAppInboxController::class, 'metaMedia']);
        Route::get('/inbox/{conversation}', [WhatsAppInboxController::class, 'conversation']);
        Route::post('/inbox/{conversation}/send', [WhatsAppInboxController::class, 'sendMessage']);
        Route::post('/inbox/{conversation}/send-media', [WhatsAppInboxController::class, 'sendMedia']);
        Route::post('/inbox/{conversation}/send-location', [WhatsAppInboxController::class, 'sendLocation']);
        Route::get('/inbox/{conversation}/messages', [WhatsAppInboxController::class, 'getMessages']);
        Route::get('/inbox/{conversation}/message-search', [WhatsAppInboxController::class, 'searchConversationMessages']);
        Route::delete('/inbox/{conversation}/messages/{message}', [WhatsAppInboxController::class, 'deleteMessage']);
        Route::post('/inbox/{conversation}/messages/{message}/react', [WhatsAppInboxController::class, 'reactMessage']);
        Route::post('/inbox/{conversation}/assign', [WhatsAppInboxController::class, 'assign']);
        Route::match(['post', 'put'], '/inbox/{conversation}/status', [WhatsAppInboxController::class, 'updateStatus']);
        Route::match(['post', 'put'], '/inbox/{conversation}/tag', [WhatsAppInboxController::class, 'toggleTag']);
        Route::match(['post', 'put'], '/inbox/{conversation}/important', [WhatsAppInboxController::class, 'setImportant']);
        Route::match(['post', 'put'], '/inbox/{conversation}/close', [WhatsAppInboxController::class, 'closeConversation']);
        Route::match(['post', 'put'], '/inbox/{conversation}/archive', [WhatsAppInboxController::class, 'archiveConversation']);
        Route::match(['post', 'put'], '/inbox/{conversation}/reopen', [WhatsAppInboxController::class, 'reopenConversation']);

        Route::post('/inbox/{conversation}/pin/{message}', [WhatsAppInboxController::class, 'pinMessage']);
        Route::post('/inbox/{conversation}/unpin', [WhatsAppInboxController::class, 'unpinMessage']);
        Route::post('/inbox/{conversation}/ai-reply', [WhatsAppInboxController::class, 'setAiReplyPreference']);
        Route::post('/inbox/{conversation}/create-lead', [LeadController::class, 'createLead']);

        // Automation Rules
        Route::apiResource('automation', AutomationController::class);
        Route::post('/automation/{rule}/toggle', [AutomationController::class, 'toggleStatus']);

        // Campaigns
        Route::apiResource('campaigns', CampaignController::class);
        Route::post('/campaigns/{campaign}/launch', [CampaignController::class, 'launch']);
        Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel']);

        // Followups
        Route::get('/followups', [WhatsAppInboxController::class, 'followupIndex']);
        Route::post('/followups', [WhatsAppInboxController::class, 'followupStore']);
        Route::put('/followups/{followup}', [WhatsAppInboxController::class, 'followupUpdate']);
        Route::post('/followups/{followup}/complete', [WhatsAppInboxController::class, 'followupComplete']);
        Route::delete('/followups/{followup}', [WhatsAppInboxController::class, 'followupDestroy']);

        // Analytics
        Route::get('/analytics', [WhatsAppInboxController::class, 'analyticsDashboard']);
        Route::get('/analytics/chart-data', [WhatsAppInboxController::class, 'analyticsChartData']);
    });
});

// Public Plan Payment Callback & Checkout Routes
Route::match(['get', 'post'], '/payment/callback', [\App\Http\Controllers\Api\ActivePackageController::class, 'handlePaymentCallback']);
Route::get('/payment/checkout/{receipt}', [\App\Http\Controllers\Api\ActivePackageController::class, 'renderCheckoutPage']);
Route::match(['get', 'post'], '/plan/payment-link/cancel', [\App\Http\Controllers\Api\ActivePackageController::class, 'cancelPaymentLink']);

// Public Invoice View & Download Route (openable in browser without bearer token)
Route::get('/invoices/{receipt}/view', [InvoiceController::class, 'publicView'])->name('invoices.public.view');
Route::get('/invoices/{receipt}/download', [InvoiceController::class, 'publicDownload'])->name('invoices.public.download');



