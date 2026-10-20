<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Services\MetaPhoneNumberRegistrationService;
use App\Services\MetaTokenRefreshService;
use App\Services\MetaWabaSubscribeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Resolve company/owner id (staff → parent). Admin may pass ?user_id=.
     */
    protected function resolveOwnerId(Request $request): int
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if (strtolower((string) ($user->role ?? '')) === 'admin' && ($request->filled('userId') || $request->filled('user_id'))) {
            return (int) $request->input('userId', $request->input('user_id'));
        }

        return (int) $user->getCompanyId();
    }

    /**
     * Settings row for this user only — never fall back to global/null userId rows.
     */
    protected function findOwnedSettings(Request $request): ?Settings
    {
        return Settings::where('userId', $this->resolveOwnerId($request))->first();
    }

    protected function getOrCreateSettings(Request $request): Settings
    {
        $ownerId = $this->resolveOwnerId($request);

        return Settings::firstOrNew(['userId' => $ownerId]);
    }

    /**
     * Deactivate only this user's WhatsApp/API configs (not every account on the platform).
     */
    protected function deactivateUserSettings(int $ownerId): void
    {
        Settings::where('userId', $ownerId)->update(['isActive' => false]);
    }

    protected function formatSettingsPayload(Settings $settings, int $ownerId): array
    {
        $payload = $settings->toArray();
        $payload['userId'] = $ownerId;
        $payload['auto_ai_enabled'] = (bool) ($settings->auto_ai_enabled ?? false);
        $payload['isActive'] = (bool) ($settings->isActive ?? false);

        $hasWabaCreds = !empty($settings->phoneNumberId) && !empty($settings->access_token);
        $credStatus = !$settings->exists 
            ? 'pending' 
            : (!$hasWabaCreds ? 'pending' : ($settings->isActive ? 'live' : 'inactive'));

        $payload['isConfigured'] = (bool) $hasWabaCreds;
        $payload['credentialsStatus'] = $credStatus;
        $payload['whatsappStatus'] = $credStatus;

        return [
            'meta' => [
                'userId' => $ownerId,
                'isConfigured' => (bool) $hasWabaCreds,
                'credentialsStatus' => $credStatus,
                'whatsappStatus' => $credStatus,
            ],
            'data' => $payload,
            'apiConfiguration' => [
                'isConfigured' => (bool) $hasWabaCreds,
                'credentialsStatus' => $credStatus,
                'whatsappStatus' => $credStatus,
                'fb_app_id' => $settings->fb_app_id,
                'fb_app_secret' => $settings->fb_app_secret,
                'phoneNumberId' => $settings->phoneNumberId,
                'waba_id' => $settings->waba_id,
                'access_token' => $settings->access_token,
                'webhook_url' => $settings->webhook_url ?: url("/api/whatsapp/webhook/{$settings->userId}"),
                'global_webhook_url' => url('/api/whatsapp/webhook'),
                'user_webhook_url' => url("/api/whatsapp/webhook/{$settings->userId}"),
                'webhook_verify_token' => $settings->webhook_secret ?: config('whatsapp.webhook_verify_token', 'test'),
                'isActive' => (bool) ($settings->isActive ?? false),
                'expires_in' => $settings->expires_in,
                'expiry_date' => $settings->expiry_date,
            ],
            'businessDetails' => [
                'business_name' => $settings->business_name,
                'business_type' => $settings->business_type,
                'industry' => $settings->industry ?: $settings->business_type,
                'business_details' => $settings->business_details,
                'services' => $settings->services,
                'products' => $settings->products,
                'phone' => $settings->support_phone,
                'email' => $settings->support_email,
                'website' => $settings->website_url,
                'location' => $settings->location,
                'address' => $settings->business_address,
                'business_hours' => $settings->business_hours,
                'working_days' => is_array($settings?->working_days) ? $settings->working_days : (is_string($settings?->working_days) ? json_decode($settings->working_days, true) ?: [] : []),
                'language' => $settings->language,
                'auto_ai_enabled' => (bool) ($settings->auto_ai_enabled ?? false),
                'auto_ai_tone' => $settings->auto_ai_tone ?? 'friendly',
                'first_time_message' => $settings->first_time_message,
            ],
            'dashboardContent' => [
                'title' => $settings->dashboard_content_title,
                'description' => $settings->dashboard_content_description,
                'image' => $settings->dashboard_content_image,
                'image_url' => Settings::formatImageUrl($settings->dashboard_content_image),
                'link' => $settings->dashboard_content_link,
            ],
            'dashboardImage' => [
                'image' => $settings->dashboard_image_image,
                'image_url' => Settings::formatImageUrl($settings->dashboard_image_image),
            ],
        ];
    }

    protected function emptySettingsPayload(int $ownerId): array
    {
        $dummy = new Settings(['userId' => $ownerId]);
        $dummy->exists = false;
        return $this->formatSettingsPayload($dummy, $ownerId);
    }

    public static function defaultFaqTemplates(): array
    {
        return [];
    }

    /**
     * Get settings for the logged-in user (API config + business details).
     */
    public function index(Request $request)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $settings = $this->findOwnedSettings($request);

            if (! $settings) {
                $formatted = $this->emptySettingsPayload($ownerId);

                return response()->json([
                    'status' => true,
                    'message' => 'No settings saved yet for this account',
                    ...$formatted,
                ]);
            }

            $formatted = $this->formatSettingsPayload($settings, $ownerId);

            return response()->json([
                'status' => true,
                ...$formatted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create or update a setting
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fb_app_id' => 'nullable|string',
            'fb_app_secret' => 'nullable|string',
            'phoneNumberId' => 'nullable|string',
            'waba_id' => 'nullable|string',
            'access_token' => 'nullable|string',
            'webhook_url' => 'nullable|string',
            'isActive' => 'nullable|boolean',
            'expires_in' => 'nullable|integer',
            'expiry_date' => 'nullable|date',
            'auto_ai_enabled' => 'nullable|boolean',
            'business_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'business_details' => 'nullable|string|max:5000',
            'business_hours' => 'nullable|string|max:255',
            'business_address' => 'nullable|string|max:1000',
            'website_url' => 'nullable|string|max:500',
            'support_phone' => 'nullable|string|max:100',
            'support_email' => 'nullable|string|max:150',
            'auto_ai_tone' => 'nullable|string|max:50',
            'first_time_message' => 'nullable|string',
            'dashboard_content_title' => 'nullable|string|max:255',
            'dashboard_content_description' => 'nullable|string',
            'dashboard_content_image' => 'nullable',
            'dashboard_content_link' => 'nullable|string|max:1000',
            'dashboard_image_image' => 'nullable',
            'industry' => 'nullable|string|max:255',
            'services' => 'nullable|string|max:5000',
            'products' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:500',
            'working_days' => 'nullable',
            'language' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:100',
            'email' => 'nullable|string|max:150',
            'website' => 'nullable|string|max:500',
            'address' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $ownerId = $this->resolveOwnerId($request);
            $settings = $this->getOrCreateSettings($request);

            $data = $request->only([
                'fb_app_id', 'fb_app_secret', 'phoneNumberId', 'waba_id',
                'access_token', 'webhook_url', 'isActive', 'expires_in', 'expiry_date',
                'auto_ai_enabled', 'business_name', 'business_type', 'business_details',
                'business_hours', 'auto_ai_tone', 'first_time_message',
                'dashboard_content_title', 'dashboard_content_description',
                'dashboard_content_link',
                'industry', 'services', 'products', 'location', 'language',
            ]);

            if ($request->has('firstTimeMessage') && empty($data['first_time_message'])) {
                $data['first_time_message'] = $request->input('firstTimeMessage');
            }

            // Handle dashboard_content_image file upload or string URL
            if ($request->hasFile('dashboard_content_image')) {
                $file = $request->file('dashboard_content_image');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                $filename = 'dc_' . time() . '_' . \Illuminate\Support\Str::uuid() . '.' . $extension;
                $uploadDir = public_path('uploads/dashboard-content');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $file->move($uploadDir, $filename);
                $isProduction = app()->isProduction();
                $data['dashboard_content_image'] = $isProduction
                    ? '/public/uploads/dashboard-content/' . $filename
                    : '/uploads/dashboard-content/' . $filename;
            } elseif ($request->has('dashboard_content_image') && is_string($request->input('dashboard_content_image'))) {
                $data['dashboard_content_image'] = $request->input('dashboard_content_image');
            }

            // Handle dashboard_image_image file upload or string URL
            if ($request->hasFile('dashboard_image_image')) {
                $file = $request->file('dashboard_image_image');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                $filename = 'di_' . time() . '_' . \Illuminate\Support\Str::uuid() . '.' . $extension;
                $uploadDir = public_path('uploads/dashboard-images');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $file->move($uploadDir, $filename);
                $isProduction = app()->isProduction();
                $data['dashboard_image_image'] = $isProduction
                    ? '/public/uploads/dashboard-images/' . $filename
                    : '/uploads/dashboard-images/' . $filename;
            } elseif ($request->has('dashboard_image_image') && is_string($request->input('dashboard_image_image'))) {
                $data['dashboard_image_image'] = $request->input('dashboard_image_image');
            }
            $data['userId'] = $ownerId;

            // Handle working_days array or json
            if ($request->has('working_days')) {
                $days = $request->input('working_days');
                $data['working_days'] = is_array($days) ? $days : (is_string($days) ? json_decode($days, true) ?: [$days] : null);
            }

            // Handle aliases seamlessly
            if ($request->filled('phone') && empty($data['support_phone'])) {
                $data['support_phone'] = $request->input('phone');
            }
            if ($request->filled('email') && empty($data['support_email'])) {
                $data['support_email'] = $request->input('email');
            }
            if ($request->filled('website') && empty($data['website_url'])) {
                $data['website_url'] = $request->input('website');
            }
            if ($request->filled('address') && empty($data['business_address'])) {
                $data['business_address'] = $request->input('address');
            }
            if ($request->filled('industry') && empty($data['business_type'])) {
                $data['business_type'] = $request->input('industry');
            }

            if ($request->boolean('isActive')) {
                $this->deactivateUserSettings($ownerId);
                $data['isActive'] = true;
            }

            $settings->fill($data);
            $settings->save();

            $formatted = $this->formatSettingsPayload($settings->fresh(), $ownerId);

            return response()->json([
                'status' => true,
                'message' => 'Settings updated successfully',
                ...$formatted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific setting (must belong to current user).
     */
    public function show(Request $request, $id)
    {
        try {
            $setting = Settings::where('userId', $this->resolveOwnerId($request))->find($id);

            if (! $setting) {
                return response()->json([
                    'status' => false,
                    'message' => 'Setting not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $setting,
                'meta' => [
                    'userId' => $this->resolveOwnerId($request),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a setting (legacy endpoint — scoped to current user).
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'value' => 'nullable|string',
            'type' => 'sometimes|string|in:string,number,boolean,json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $setting = Settings::where('userId', $this->resolveOwnerId($request))->find($id);

            if (! $setting) {
                return response()->json([
                    'status' => false,
                    'message' => 'Setting not found',
                ], 404);
            }

            $setting->update($request->only(['value', 'type']));

            return response()->json([
                'status' => true,
                'message' => 'Setting updated successfully',
                'data' => $setting,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a setting (must belong to current user).
     */
    public function destroy(Request $request, $id)
    {
        try {
            $setting = Settings::where('userId', $this->resolveOwnerId($request))->find($id);

            if (! $setting) {
                return response()->json([
                    'status' => false,
                    'message' => 'Setting not found',
                ], 404);
            }

            $setting->delete();

            return response()->json([
                'status' => true,
                'message' => 'Setting deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Configure WhatsApp Business API settings for this user only.
     */
    public function configureWhatsApp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'phoneNumberId' => 'required|string',
            'waba_id' => 'required|string',
            'fb_app_id' => 'required|string',
            'fb_app_secret' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $ownerId = $this->resolveOwnerId($request);
            $this->deactivateUserSettings($ownerId);

            $settings = Settings::updateOrCreate(
                ['userId' => $ownerId],
                [
                    'access_token' => $request->access_token,
                    'phoneNumberId' => $request->phoneNumberId,
                    'waba_id' => $request->waba_id,
                    'fb_app_id' => $request->fb_app_id,
                    'fb_app_secret' => $request->fb_app_secret,
                    'isActive' => true,
                ]
            );

            $formatted = $this->formatSettingsPayload($settings->fresh(), $ownerId);

            return response()->json([
                'status' => true,
                'message' => 'WhatsApp configuration saved successfully',
                ...$formatted,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh Meta token for the logged-in user's settings.
     */
    public function refreshToken(Request $request)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $metaTokenService = new MetaTokenRefreshService();
            $result = $metaTokenService->refreshMetaToken(null, $ownerId);

            return response()->json([
                'status' => true,
                'message' => 'Token refreshed successfully',
                'data' => $result,
                'meta' => [
                    'userId' => $ownerId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get WhatsApp configuration for the logged-in user only.
     */
    public function getWhatsAppConfig(Request $request)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);
            $settings = Settings::where('userId', $ownerId)->where('isActive', true)->first()
                ?: $this->findOwnedSettings($request);

            if (! $settings) {
                return response()->json([
                    'status' => false,
                    'message' => 'No WhatsApp configuration found for this account',
                    'meta' => [
                        'userId' => $ownerId,
                    ],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'meta' => [
                    'userId' => $ownerId,
                ],
                'data' => [
                    'phoneNumberId' => $settings->phoneNumberId,
                    'waba_id' => $settings->waba_id,
                    'fb_app_id' => $settings->fb_app_id,
                    'expires_in' => $settings->expires_in,
                    'expiry_date' => $settings->expiry_date,
                    'isActive' => (bool) $settings->isActive,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Connect Facebook account for this user only.
     */
    public function connectFacebook(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'waba_id' => 'required|string',
            'phoneNumberId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $ownerId = $this->resolveOwnerId($request);
            $settings = $this->getOrCreateSettings($request);

            if ($request->boolean('isActive', true)) {
                $this->deactivateUserSettings($ownerId);
            }

            $settings->fill([
                'access_token' => $request->access_token,
                'waba_id' => $request->waba_id,
                'phoneNumberId' => $request->phoneNumberId,
                'isActive' => true,
                'userId' => $ownerId,
            ]);
            $settings->save();

            $formatted = $this->formatSettingsPayload($settings->fresh(), $ownerId);

            return response()->json([
                'status' => true,
                'message' => 'Facebook account connected successfully',
                ...$formatted,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save API configuration + business details for this user only.
     */
    public function saveSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fb_app_id' => 'nullable|string',
            'fb_app_secret' => 'nullable|string',
            'phoneNumberId' => 'nullable|string',
            'waba_id' => 'nullable|string',
            'access_token' => 'nullable|string',
            'webhook_url' => 'nullable|string',
            'isActive' => 'nullable|boolean',
            'expires_in' => 'nullable|integer',
            'expiry_date' => 'nullable|date',
            'auto_ai_enabled' => 'nullable|boolean',
            'business_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'business_details' => 'nullable|string|max:5000',
            'business_hours' => 'nullable|string|max:255',
            'auto_ai_tone' => 'nullable|string|max:50',
            'first_time_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $ownerId = $this->resolveOwnerId($request);
            $settings = $this->getOrCreateSettings($request);

            $data = $request->only([
                'fb_app_id', 'fb_app_secret', 'phoneNumberId', 'waba_id',
                'access_token', 'webhook_url', 'isActive', 'expires_in', 'expiry_date',
                'auto_ai_enabled', 'business_name', 'business_type', 'business_details',
                'business_hours', 'auto_ai_tone', 'first_time_message',
            ]);

            if ($request->has('firstTimeMessage') && empty($data['first_time_message'])) {
                $data['first_time_message'] = $request->input('firstTimeMessage');
            }
            $data['userId'] = $ownerId;

            if ($request->boolean('isActive')) {
                $this->deactivateUserSettings($ownerId);
                $data['isActive'] = true;
            }

            $settings->fill($data);
            $settings->save();

            $formatted = $this->formatSettingsPayload($settings->fresh(), $ownerId);

            return response()->json([
                'status' => true,
                'message' => 'Settings saved successfully',
                ...$formatted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update business details / Auto AI profile for this user only.
     */
    public function updateAutoAi(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'business_details' => 'nullable|string|max:5000',
            'services' => 'nullable|string|max:5000',
            'products' => 'nullable|string|max:5000',
            'phone' => 'nullable|string|max:100',
            'email' => 'nullable|string|max:150',
            'website' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:500',
            'address' => 'nullable|string|max:1000',
            'business_hours' => 'nullable|string|max:255',
            'working_days' => 'nullable',
            'language' => 'nullable|string|max:100',
            'auto_ai_enabled' => 'nullable|boolean',
            'auto_ai_tone' => 'nullable|string|max:50',
            'first_time_message' => 'nullable|string',
            // Safe column aliases
            'support_phone' => 'nullable|string|max:100',
            'support_email' => 'nullable|string|max:150',
            'website_url' => 'nullable|string|max:500',
            'business_address' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $ownerId = $this->resolveOwnerId($request);
            $settings = $this->getOrCreateSettings($request);

            $data = $request->only([
                'business_name',
                'business_type',
                'industry',
                'business_details',
                'services',
                'products',
                'location',
                'business_hours',
                'language',
                'auto_ai_enabled',
                'auto_ai_tone',
                'first_time_message',
            ]);

            if ($request->has('firstTimeMessage') && empty($data['first_time_message'])) {
                $data['first_time_message'] = $request->input('firstTimeMessage');
            }

            // Handle aliases seamlessly
            if ($request->filled('phone')) {
                $data['support_phone'] = $request->input('phone');
            } elseif ($request->filled('support_phone')) {
                $data['support_phone'] = $request->input('support_phone');
            }

            if ($request->filled('email')) {
                $data['support_email'] = $request->input('email');
            } elseif ($request->filled('support_email')) {
                $data['support_email'] = $request->input('support_email');
            }

            if ($request->filled('website')) {
                $data['website_url'] = $request->input('website');
            } elseif ($request->filled('website_url')) {
                $data['website_url'] = $request->input('website_url');
            }

            if ($request->filled('address')) {
                $data['business_address'] = $request->input('address');
            } elseif ($request->filled('business_address')) {
                $data['business_address'] = $request->input('business_address');
            }

            if ($request->filled('industry') && empty($data['business_type'])) {
                $data['business_type'] = $request->input('industry');
            }

            foreach ($data as $key => $value) {
                if ($request->exists($key) || array_key_exists($key, $data)) {
                    $settings->{$key} = $value;
                }
            }

            if ($request->has('working_days')) {
                $days = $request->input('working_days');
                $settings->working_days = is_array($days) ? $days : (is_string($days) ? json_decode($days, true) ?: [$days] : null);
            }

            $settings->userId = $ownerId;
            $settings->save();

            $formatted = $this->formatSettingsPayload($settings->fresh(), $ownerId);

            return response()->json([
                'status' => true,
                'message' => 'Auto AI settings updated successfully',
                ...$formatted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register this user's WhatsApp Phone Number on Meta Cloud API with a 6-digit PIN.
     * POST /api/settings/register-phone
     * Body: { "pin": "123456", "phoneNumberId": "optional", "access_token": "optional" }
     */
    public function registerPhoneNumber(Request $request)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);

            $pin = trim((string) $request->input('pin', ''));
            if ($pin === '' || !preg_match('/^\d{6}$/', $pin)) {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'Two-step verification PIN is required and must be exactly 6 digits (e.g. 123456).',
                ], 422);
            }

            $settings = $this->getOrCreateSettings($request);

            $phoneNumberId = trim((string) ($request->input('phoneNumberId') ?: $request->input('phone_number_id') ?: $settings->phoneNumberId));
            $accessToken = trim((string) ($request->input('access_token') ?: $request->input('accessToken') ?: $settings->access_token));

            if ($phoneNumberId === '' || $accessToken === '') {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'Both Phone Number ID and WhatsApp Access Token are required. Please save credentials in settings or provide them in the request.',
                ], 422);
            }

            $updatedCreds = false;
            if ($request->filled('phoneNumberId') || $request->filled('phone_number_id')) {
                $settings->phoneNumberId = $phoneNumberId;
                $updatedCreds = true;
            }
            if ($request->filled('access_token') || $request->filled('accessToken')) {
                $settings->access_token = $accessToken;
                $updatedCreds = true;
            }
            if ($request->filled('waba_id')) {
                $settings->waba_id = trim((string) $request->input('waba_id'));
                $updatedCreds = true;
            }

            $regService = app(MetaPhoneNumberRegistrationService::class);
            $result = $regService->register($phoneNumberId, $accessToken, $pin, $ownerId);

            if ($result['success'] ?? false) {
                $settings->isActive = true;
                $settings->userId = $ownerId;
                $settings->save();

                $wabaSubscription = null;
                if (!empty($settings->waba_id)) {
                    $wabaSubscription = app(MetaWabaSubscribeService::class)->subscribe($settings);
                }

                return response()->json([
                    'success' => true,
                    'status' => true,
                    'message' => 'WhatsApp Phone Number registered and connected successfully on Meta Cloud API.',
                    'data' => [
                        'phone_number_id' => $phoneNumberId,
                        'registration' => $result,
                        'phone_status' => $result['phone_status'] ?? null,
                        'waba_subscription' => $wabaSubscription,
                    ],
                ], 200);
            }

            if ($updatedCreds) {
                $settings->userId = $ownerId;
                $settings->save();
            }

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $result['message'] ?? 'Failed to register phone number on Meta Cloud API.',
                'error_code' => $result['error_code'] ?? null,
                'meta_error' => $result['meta_error'] ?? null,
            ], 422);
        } catch (\Exception $e) {
            Log::error('SettingsController@registerPhoneNumber failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Error registering phone number: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Query live status of WhatsApp Phone Number on Meta Cloud API.
     * GET /api/settings/phone-status
     */
    public function getPhoneStatus(Request $request)
    {
        try {
            $settings = $this->findOwnedSettings($request);

            $phoneNumberId = trim((string) ($request->query('phoneNumberId') ?: ($settings->phoneNumberId ?? '')));
            $accessToken = trim((string) ($request->query('access_token') ?: ($settings->access_token ?? '')));

            if ($phoneNumberId === '' || $accessToken === '') {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'Phone Number ID and Access Token are not configured.',
                ], 422);
            }

            $regService = app(MetaPhoneNumberRegistrationService::class);
            $result = $regService->getPhoneStatus($phoneNumberId, $accessToken);

            return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
        } catch (\Exception $e) {
            Log::error('SettingsController@getPhoneStatus failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Error querying phone status: ' . $e->getMessage(),
            ], 500);
        }
    }
}
