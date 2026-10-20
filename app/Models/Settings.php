<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    use HasFactory;

    protected $fillable = [
        'userId',
        'fb_app_id',
        'fb_app_secret',
        'phoneNumberId',
        'waba_id',
        'access_token',
        'webhook_url',
        'isActive',
        'auto_ai_enabled',
        'business_name',
        'business_type',
        'business_details',
        'business_hours',
        'auto_ai_tone',
        'first_time_message',
        'expires_in',
        'expiry_date',
        'batch_size',
        'batch_delay_seconds',
        'enable_batch_processing',
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'smtp_password',
        'smtp_cc_email',
        'smtp_cc_enabled',
        'sms_gateway_url',
        'sms_api_key',
        'webhook_secret',
        'razorpay_key_id',
        'razorpay_key_secret',
        'razorpay_webhook_secret',
        'wallet_min_balance',
        'wallet_price_marketing',
        'wallet_price_utility',
        'wallet_price_authentication',
        'is_app_live',
        'gst_percentage',
        'gst_enabled',
        'gstin',
        'gst_state',
        'dashboard_content_title',
        'dashboard_content_description',
        'dashboard_content_image',
        'dashboard_content_link',
        'dashboard_content_status',
        'dashboard_image_image',
        'dashboard_image_status',
        'business_address',
        'website_url',
        'support_phone',
        'support_email',
        'industry',
        'services',
        'products',
        'location',
        'working_days',
        'language',
    ];

    protected $hidden = [
        'fb_app_secret',
        'ai_api_key',
        'ai_model',
        'ai_provider',
        'ai_fallback_message',
        'custom_instructions',
        'business_keywords',
        'delivery_info',
        'payment_methods',
        'return_policy',
        'order_process',
        'google_maps_link',
        'faqs',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'is_app_live' => 'boolean',
        'auto_ai_enabled' => 'boolean',
        'gst_enabled' => 'boolean',
        'smtp_cc_enabled' => 'boolean',
        'gst_percentage' => 'decimal:2',
        'expires_in' => 'integer',
        'expiry_date' => 'datetime',
        'batch_size' => 'integer',
        'batch_delay_seconds' => 'integer',
        'enable_batch_processing' => 'boolean',
        'dashboard_content_status' => 'integer',
        'dashboard_image_status' => 'integer',
        'working_days' => 'array',
    ];

    /**
     * Get global GST percentage (defaults to 18% if enabled, 0 if disabled).
     */
    public static function getGstPercentage(): float
    {
        try {
            $setting = static::whereNull('userId')->first() ?: static::first();
            if ($setting) {
                if (isset($setting->gst_enabled) && !$setting->gst_enabled) {
                    return 0.0;
                }
                if (isset($setting->gst_percentage) && is_numeric($setting->gst_percentage)) {
                    return (float) $setting->gst_percentage;
                }
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return 18.0;
    }

    /**
     * Check if GST is enabled globally.
     */
    public static function isGstEnabled(): bool
    {
        try {
            $setting = static::whereNull('userId')->first() ?: static::first();
            if ($setting && isset($setting->gst_enabled)) {
                return (bool) $setting->gst_enabled;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return true;
    }

    /**
     * Get full global GST tax configuration.
     */
    public static function getGstConfig(): array
    {
        try {
            $setting = static::whereNull('userId')->first() ?: static::first();
            $enabled = $setting && isset($setting->gst_enabled) ? (bool) $setting->gst_enabled : true;
            $percentage = $setting && isset($setting->gst_percentage) && is_numeric($setting->gst_percentage)
                ? (float) $setting->gst_percentage
                : 18.0;

            return [
                'enabled' => $enabled,
                'percentage' => round($percentage, 2),
                'label' => $enabled ? "GST (" . round($percentage, 2) . "%)" : "Disabled (0%)",
                'gstin' => $setting->gstin ?? '',
                'state' => $setting->gst_state ?? '',
            ];
        } catch (\Throwable $e) {
            return [
                'enabled' => true,
                'percentage' => 18.0,
                'label' => 'GST (18%)',
                'gstin' => '',
                'state' => '',
            ];
        }
    }

    /**
     * Get global is_app_live status safely.
     */
    public static function getAppLiveStatus(): bool
    {
        try {
            $setting = static::whereNull('userId')->first() ?: static::first();
            if ($setting && isset($setting->is_app_live)) {
                return (bool) $setting->is_app_live;
            }
        } catch (\Throwable $e) {
            // fallback if table/column uninitialized
        }

        return true;
    }

    /**
     * Update global is_app_live status safely.
     */
    public static function setAppLiveStatus(bool $status): bool
    {
        try {
            $setting = static::whereNull('userId')->first() ?: static::first();
            if ($setting) {
                $setting->is_app_live = $status;
                $setting->save();
            } else {
                static::create([
                    'userId' => null,
                    'is_app_live' => $status,
                    'isActive' => true,
                ]);
            }

            return $status;
        } catch (\Throwable $e) {
            return $status;
        }
    }

    /**
     * Helper to compute absolute URL for stored images.
     */
    public static function formatImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        $imagePath = $image;
        $isProduction = app()->isProduction();
        if ($isProduction && !str_contains($imagePath, '/public/')) {
            $imagePath = '/public' . (str_starts_with($imagePath, '/') ? $imagePath : '/' . $imagePath);
        }

        return rtrim(config('app.url'), '/') . '/' . ltrim($imagePath, '/');
    }

    /**
     * Get the user that owns the settings.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Get decoded FAQs array safely (supports JSON array or string Q&A).
     */
    public function getParsedFaqs(): array
    {
        if (empty($this->faqs)) {
            return [];
        }

        if (is_array($this->faqs)) {
            return $this->faqs;
        }

        $decoded = json_decode((string) $this->faqs, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Support plain text Q&A format (lines starting with Q: / A:)
        $lines = preg_split('/\r\n|\r|\n/', (string) $this->faqs);
        $faqs = [];
        $currentQ = null;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^(?:Q|Question)\s*[:\.\-]\s*(.+)$/i', $trimmed, $m)) {
                $currentQ = trim($m[1]);
            } elseif ($currentQ && preg_match('/^(?:A|Answer)\s*[:\.\-]\s*(.+)$/i', $trimmed, $m)) {
                $faqs[] = ['question' => $currentQ, 'answer' => trim($m[1])];
                $currentQ = null;
            } elseif ($currentQ) {
                $faqs[] = ['question' => $currentQ, 'answer' => $trimmed];
                $currentQ = null;
            }
        }

        return $faqs;
    }

    /**
     * Get valid CC email addresses as an array.
     * Supports single email or multiple emails separated by comma, semicolon, space, or newline.
     * e.g. "admin@zapreach.com,billing@zapreach.com"
     */
    public function getCcEmails(): array
    {
        if (empty($this->smtp_cc_email)) {
            return [];
        }

        $parts = preg_split('/[,;\s]+/', (string) $this->smtp_cc_email);
        $valid = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (!empty($p) && filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $valid[] = strtolower($p);
            }
        }

        return array_values(array_unique($valid));
    }

    /**
     * Get CC emails as a clean comma-separated string.
     */
    public function getCcEmailsString(): string
    {
        return implode(', ', $this->getCcEmails());
    }
}
