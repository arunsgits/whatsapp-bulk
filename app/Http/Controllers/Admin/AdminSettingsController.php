<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminSettingsController extends Controller
{
    /**
     * Get Global Credentials
     * GET /api/v1/settings/credentials or /api/v1/admin/settings/credentials
     */
    public function getCredentials(Request $request)
    {
        try {
            $settings = Settings::whereNull('userId')->first() ?: Settings::first() ?: new Settings();

            return response()->json([
                'success' => true,
                'data' => [
                    'smtp' => [
                        'smtpHost' => $settings->smtp_host ?? 'smtp.gmail.com',
                        'smtpPort' => (int) ($settings->smtp_port ?? 465),
                        'smtpUser' => $settings->smtp_user ?? 'alerts@zapreach.com',
                        'smtpPassword' => $settings->smtp_password ? '********' : '',
                        'smtpCcEmail' => $settings->smtp_cc_email ?? '',
                        'smtpCcEnabled' => (bool) ($settings->smtp_cc_enabled ?? false),
                        'ccEmail' => $settings->smtp_cc_email ?? '',
                        'ccEnabled' => (bool) ($settings->smtp_cc_enabled ?? false),
                    ],
                    'sms' => [
                        'smsGatewayUrl' => $settings->sms_gateway_url ?? 'https://api.twilio.com/v1/Messages.json',
                        'smsApiKey' => $settings->sms_api_key ? '********' : '',
                    ],
                    'webhook' => [
                        'webhookUrl' => $settings->webhook_url ?? (config('app.url') . '/api/v1/whatsapp/webhook'),
                        'webhookSecret' => $settings->webhook_secret ? '********' : '',
                    ],
                    'razorpay' => [
                        'keyId' => $settings->razorpay_key_id ?: config('services.razorpay.key'),
                        'keySecret' => ($settings->razorpay_key_secret || config('services.razorpay.secret')) ? '********' : '',
                        'configured' => (bool) (($settings->razorpay_key_id ?: config('services.razorpay.key')) && ($settings->razorpay_key_secret ?: config('services.razorpay.secret'))),
                    ],
                    'gst' => app(\App\Services\WalletService::class)->gstConfig(),
                    'walletPricing' => app(\App\Services\WalletService::class)->pricingConfig(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving global credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SMTP Settings
     * GET /api/v1/settings/credentials/smtp or /api/v1/admin/settings/credentials/smtp
     */
    public function getSmtp(Request $request)
    {
        try {
            $settings = Settings::whereNull('userId')->first() ?: Settings::first() ?: new Settings();

            return response()->json([
                'success' => true,
                'status' => true,
                'data' => [
                    'smtpHost' => $settings->smtp_host ?? 'smtp.gmail.com',
                    'smtpPort' => (int) ($settings->smtp_port ?? 465),
                    'smtpUser' => $settings->smtp_user ?? 'alerts@zapreach.com',
                    'smtpPassword' => $settings->smtp_password ? '********' : '',
                    'smtpCcEmail' => $settings->smtp_cc_email ?? '',
                    'smtpCcEnabled' => (bool) ($settings->smtp_cc_enabled ?? false),
                    'ccEmail' => $settings->smtp_cc_email ?? '',
                    'ccEnabled' => (bool) ($settings->smtp_cc_enabled ?? false),
                    'is_configured' => !empty($settings->smtp_host) && !empty($settings->smtp_user),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Error retrieving SMTP settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update SMTP Settings
     * PUT/POST /api/v1/settings/credentials/smtp or /api/v1/admin/settings/credentials/smtp
     */
    public function updateSmtp(Request $request)
    {
        $existing = Settings::whereNull('userId')->first() ?: Settings::first();
        $smtpInput = $request->input('smtp', []);
        $host = $request->input('smtpHost') ?? $request->input('smtp_host') ?? ($smtpInput['smtpHost'] ?? ($smtpInput['smtp_host'] ?? ($existing?->smtp_host ?? null)));
        $port = $request->input('smtpPort') ?? $request->input('smtp_port') ?? ($smtpInput['smtpPort'] ?? ($smtpInput['smtp_port'] ?? ($existing?->smtp_port ?? null)));
        $user = $request->input('smtpUser') ?? $request->input('smtp_user') ?? ($smtpInput['smtpUser'] ?? ($smtpInput['smtp_user'] ?? ($existing?->smtp_user ?? null)));
        $pass = $request->input('smtpPassword') ?? $request->input('smtp_password') ?? ($smtpInput['smtpPassword'] ?? ($smtpInput['smtp_password'] ?? null));

        $ccEmail = $request->input('smtpCcEmail')
            ?? $request->input('smtp_cc_email')
            ?? $request->input('ccEmail')
            ?? $request->input('cc_email')
            ?? $request->input('admin_cc_email')
            ?? ($smtpInput['smtpCcEmail'] ?? ($smtpInput['smtp_cc_email'] ?? ($smtpInput['ccEmail'] ?? ($smtpInput['cc_email'] ?? null))));

        $ccEnabledRaw = $request->input('smtpCcEnabled')
            ?? $request->input('smtp_cc_enabled')
            ?? $request->input('ccEnabled')
            ?? $request->input('cc_enabled')
            ?? $request->input('is_cc_enabled')
            ?? ($smtpInput['smtpCcEnabled'] ?? ($smtpInput['smtp_cc_enabled'] ?? ($smtpInput['ccEnabled'] ?? ($smtpInput['cc_enabled'] ?? null))));

        $merged = [
            'smtpHost' => $host,
            'smtpPort' => $port,
            'smtpUser' => $user,
            'smtpPassword' => $pass,
            'smtpCcEmail' => $ccEmail,
        ];

        $validator = Validator::make($merged, [
            'smtpHost' => 'required|string',
            'smtpPort' => 'required|numeric',
            'smtpUser' => 'required|string',
            'smtpPassword' => 'nullable|string',
            'smtpCcEmail' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $settings = $existing ?: new Settings();

            $updateData = [
                'smtp_host' => $host,
                'smtp_port' => (int) $port,
                'smtp_user' => $user,
            ];

            if (!empty($pass) && $pass !== '********') {
                $updateData['smtp_password'] = $pass;
            }

            if ($ccEmail !== null) {
                $rawCc = trim((string) $ccEmail);
                if ($rawCc !== '') {
                    $parts = preg_split('/[,;\s]+/', $rawCc);
                    $cleaned = [];
                    $invalid = [];
                    foreach ($parts as $p) {
                        $p = trim($p);
                        if ($p === '') continue;
                        if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
                            $cleaned[] = strtolower($p);
                        } else {
                            $invalid[] = $p;
                        }
                    }

                    if (!empty($invalid)) {
                        return response()->json([
                            'success' => false,
                            'status' => false,
                            'message' => 'Invalid email address in CC list: ' . implode(', ', $invalid) . '. Please provide valid email addresses separated by comma.',
                        ], 400);
                    }

                    $updateData['smtp_cc_email'] = implode(',', array_unique($cleaned));
                } else {
                    $updateData['smtp_cc_email'] = null;
                }
            }

            if ($ccEnabledRaw !== null) {
                $updateData['smtp_cc_enabled'] = filter_var($ccEnabledRaw, FILTER_VALIDATE_BOOLEAN);
            }

            if ($settings->exists) {
                $settings->update($updateData);
            } else {
                $settings = Settings::create($updateData);
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'SMTP settings updated successfully',
                'data' => [
                    'smtpHost' => $settings->smtp_host,
                    'smtpPort' => (int) $settings->smtp_port,
                    'smtpUser' => $settings->smtp_user,
                    'smtpPassword' => $settings->smtp_password ? '********' : '',
                    'smtpCcEmail' => $settings->smtp_cc_email ?? '',
                    'smtpCcEnabled' => (bool) ($settings->smtp_cc_enabled ?? false),
                    'ccEmail' => $settings->smtp_cc_email ?? '',
                    'ccEnabled' => (bool) ($settings->smtp_cc_enabled ?? false),
                    'is_configured' => true,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Error updating SMTP settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test SMTP Connection
     * POST /api/v1/settings/credentials/smtp/test or /api/v1/admin/settings/credentials/smtp/test
     */
    public function testSmtp(Request $request)
    {
        try {
            $settings = Settings::whereNull('userId')->first() ?: Settings::first();
            if (!$settings || empty($settings->smtp_host) || empty($settings->smtp_user)) {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'SMTP settings are not configured yet.'
                ], 400);
            }

            $ccList = $settings->getCcEmailsString();
            $ccStatus = $settings->smtp_cc_enabled && $ccList !== ''
                ? " [CC to Admin: {$ccList}]"
                : ($ccList !== '' ? " [CC disabled: {$ccList}]" : '');

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => "SMTP settings validated successfully (Host: {$settings->smtp_host}, Port: {$settings->smtp_port}, User: {$settings->smtp_user}){$ccStatus}",
                'data' => [
                    'smtpHost' => $settings->smtp_host,
                    'smtpPort' => (int) $settings->smtp_port,
                    'smtpUser' => $settings->smtp_user,
                    'smtpCcEmail' => $settings->smtp_cc_email ?? '',
                    'smtpCcEnabled' => (bool) ($settings->smtp_cc_enabled ?? false),
                    'ccEmail' => $settings->smtp_cc_email ?? '',
                    'ccEnabled' => (bool) ($settings->smtp_cc_enabled ?? false),
                    'configured' => true,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'SMTP test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update SMS Gateway Settings
     * PUT /api/v1/settings/credentials/sms or /api/v1/admin/settings/credentials/sms
     */
    public function updateSms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'smsGatewayUrl' => 'required|string|url',
            'smsApiKey' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $settings = Settings::whereNull('userId')->first() ?: Settings::first() ?: new Settings();

            $updateData = [
                'sms_gateway_url' => $request->smsGatewayUrl,
            ];

            if ($request->filled('smsApiKey') && $request->smsApiKey !== '********') {
                $updateData['sms_api_key'] = $request->smsApiKey;
            }

            if ($settings->exists) {
                $settings->update($updateData);
            } else {
                Settings::create($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'SMS Gateway settings updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating SMS settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Webhook Settings
     * PUT /api/v1/settings/credentials/webhook or /api/v1/admin/settings/credentials/webhook
     */
    public function updateWebhook(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'webhookUrl' => 'required|string|url',
            'webhookSecret' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $settings = Settings::whereNull('userId')->first() ?: Settings::first() ?: new Settings();

            $updateData = [
                'webhook_url' => $request->webhookUrl,
            ];

            if ($request->filled('webhookSecret') && $request->webhookSecret !== '********') {
                $updateData['webhook_secret'] = $request->webhookSecret;
            }

            if ($settings->exists) {
                $settings->update($updateData);
            } else {
                Settings::create($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook settings updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating webhook settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rotate Webhook Secret
     * POST /api/v1/settings/credentials/webhook/rotate-secret or /api/v1/admin/settings/credentials/webhook/rotate-secret
     */
    public function rotateWebhookSecret(Request $request)
    {
        try {
            $newSecret = 'whsec_' . bin2hex(random_bytes(16));
            $settings = Settings::whereNull('userId')->first() ?: Settings::first() ?: new Settings();

            if ($settings->exists) {
                $settings->update(['webhook_secret' => $newSecret]);
            } else {
                Settings::create(['webhook_secret' => $newSecret]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'webhookSecret' => $newSecret
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rotating webhook secret: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Razorpay keys used for user wallet recharge
     * PUT /api/v1/admin/settings/credentials/razorpay
     */
    public function updateRazorpay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyId' => 'required|string',
            'keySecret' => 'nullable|string',
            'webhookSecret' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            app(\App\Services\WalletService::class)->ensureSchema();
            $settings = Settings::whereNull('userId')->first() ?: Settings::first() ?: new Settings();

            $updateData = [
                'razorpay_key_id' => $request->keyId,
            ];

            if ($request->filled('keySecret') && $request->keySecret !== '********') {
                $updateData['razorpay_key_secret'] = $request->keySecret;
            }
            if ($request->filled('webhookSecret') && $request->webhookSecret !== '********') {
                $updateData['razorpay_webhook_secret'] = $request->webhookSecret;
            }
            if ($request->hasAny(['percentage', 'gstPercentage', 'gst_percentage'])) {
                $pct = $request->input('percentage', $request->input('gstPercentage', $request->input('gst_percentage')));
                if ($pct !== null && is_numeric($pct)) {
                    $updateData['gst_percentage'] = round((float) $pct, 2);
                }
            }
            if ($request->hasAny(['gstEnabled', 'gst_enabled'])) {
                $updateData['gst_enabled'] = (bool) $request->boolean('gstEnabled', $request->boolean('gst_enabled', true));
            }

            if ($settings->exists) {
                $settings->update($updateData);
            } else {
                Settings::create($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Razorpay settings updated successfully',
                'data' => [
                    'gst' => app(\App\Services\WalletService::class)->gstConfig()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating Razorpay settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Global GST Tax Settings
     * GET /api/v1/settings/credentials/gst or /api/v1/admin/settings/credentials/gst
     */
    public function getGst(Request $request)
    {
        try {
            $gst = app(\App\Services\WalletService::class)->gstConfig();
            return response()->json([
                'success' => true,
                'data' => $gst
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving GST settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Global GST Tax Settings (Rate, Enabled/Disabled, GSTIN)
     * PUT/POST /api/v1/settings/credentials/gst or /api/v1/admin/settings/credentials/gst
     */
    public function updateGst(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'percentage' => 'nullable|numeric|min:0|max:100',
            'gstPercentage' => 'nullable|numeric|min:0|max:100',
            'gst_percentage' => 'nullable|numeric|min:0|max:100',
            'enabled' => 'nullable|boolean',
            'gstEnabled' => 'nullable|boolean',
            'gst_enabled' => 'nullable|boolean',
            'gstin' => 'nullable|string|max:30',
            'state' => 'nullable|string|max:100',
            'gst_state' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $wallet = app(\App\Services\WalletService::class);
            $wallet->ensureSchema();
            $settings = Settings::whereNull('userId')->first() ?: Settings::first() ?: new Settings();

            $updateData = [];

            // Percentage (supports 18, 0, or any custom rate admin puts)
            $pctInput = $request->input('percentage', $request->input('gstPercentage', $request->input('gst_percentage')));
            if ($pctInput !== null && is_numeric($pctInput)) {
                $updateData['gst_percentage'] = round((float) $pctInput, 2);
            }

            // Enabled / Disabled
            if ($request->hasAny(['enabled', 'gstEnabled', 'gst_enabled'])) {
                $updateData['gst_enabled'] = (bool) $request->boolean('enabled', $request->boolean('gstEnabled', $request->boolean('gst_enabled', true)));
            }

            // Optional GSTIN and State
            if ($request->has('gstin')) {
                $updateData['gstin'] = trim((string) $request->input('gstin'));
            }
            if ($request->hasAny(['state', 'gst_state'])) {
                $updateData['gst_state'] = trim((string) $request->input('state', $request->input('gst_state')));
            }

            if (empty($updateData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No GST configuration fields provided to update.'
                ], 400);
            }

            if ($settings->exists) {
                $settings->update($updateData);
            } else {
                Settings::create($updateData);
            }

            $currentConfig = $wallet->gstConfig();

            return response()->json([
                'success' => true,
                'message' => 'GST tax settings updated successfully',
                'data' => $currentConfig
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating GST settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update wallet billing prices (bulk delivery charges)
     * PUT /api/v1/admin/settings/credentials/wallet-pricing
     */
    public function updateWalletPricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'minBalance' => 'nullable|numeric|min:0',
            'min_balance' => 'nullable|numeric|min:0',
            'priceMarketing' => 'nullable|numeric|min:0',
            'marketing' => 'nullable|numeric|min:0',
            'priceUtility' => 'nullable|numeric|min:0',
            'utility' => 'nullable|numeric|min:0',
            'priceAuthentication' => 'nullable|numeric|min:0',
            'authentication' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $wallet = app(\App\Services\WalletService::class);
            $wallet->ensureSchema();
            $settings = Settings::whereNull('userId')->first() ?: Settings::first() ?: new Settings();

            $updateData = [];
            if ($request->filled('minBalance') || $request->filled('min_balance')) {
                $updateData['wallet_min_balance'] = (float) $request->input('minBalance', $request->input('min_balance'));
            }
            if ($request->filled('priceMarketing') || $request->filled('marketing')) {
                $updateData['wallet_price_marketing'] = (float) $request->input('priceMarketing', $request->input('marketing'));
            }
            if ($request->filled('priceUtility') || $request->filled('utility')) {
                $updateData['wallet_price_utility'] = (float) $request->input('priceUtility', $request->input('utility'));
            }
            if ($request->filled('priceAuthentication') || $request->filled('authentication')) {
                $updateData['wallet_price_authentication'] = (float) $request->input('priceAuthentication', $request->input('authentication'));
            }

            if (empty($updateData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pricing fields provided',
                ], 400);
            }

            if ($settings->exists) {
                $settings->update($updateData);
            } else {
                Settings::create($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Wallet pricing updated successfully',
                'data' => $wallet->pricingConfig(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating wallet pricing: ' . $e->getMessage()
            ], 500);
        }
    }
}
