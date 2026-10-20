<?php

namespace App\Services;

use App\Models\Settings;
use App\Models\Template;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * WhatsApp credentials for this user (phone number ID + token).
     * Does not fall back to another user's settings.
     */
    public function resolveSettingsForUser($userId = null): ?Settings
    {
        if ($userId) {
            return Settings::where('userId', $userId)->where('isActive', true)->first()
                ?: Settings::where('userId', $userId)->first();
        }

        return Settings::where('isActive', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function sendCredentials(?Settings $settings, $userId = null): array
    {
        if (!$settings) {
            throw new \Exception('No WhatsApp settings found for this user');
        }

        $phoneNumberId = trim((string) ($settings->phoneNumberId ?? ''));
        $accessToken = trim((string) ($settings->access_token ?? ''));

        if ($phoneNumberId === '' || $accessToken === '') {
            Log::error('❌ WhatsApp credentials missing for send', [
                'userId' => $userId ?? $settings->userId,
                'has_phoneNumberId' => $phoneNumberId !== '',
                'has_access_token' => $accessToken !== '',
            ]);
            throw new \Exception('WhatsApp Phone Number ID or access token is missing for this user');
        }

        return [$phoneNumberId, $accessToken];
    }

    /**
     * Send a simple WhatsApp message
     */
    public function sendWhatsAppMessage($phone, $name = null, $userId = null)
    {
        try {
            $settings = $this->resolveSettingsForUser($userId);

            if (!$settings) {
                Log::error('❌ No WhatsApp settings found in DB.', ['userId' => $userId]);
                return false;
            }

            [$phoneNumberId, $accessToken] = $this->sendCredentials($settings, $userId);

            // Debug logging
            Log::info('WhatsApp Settings Debug:', [
                'phoneNumberId' => $phoneNumberId,
                'accessToken' => $accessToken ? 'SET' : 'NOT SET',
                'waba_id' => $settings->waba_id,
                'isActive' => $settings->isActive
            ]);
            $templateName = "opt_in_welcome_template";

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'whatsapp_marketing_optin' => true,
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $name ?: 'there'],
                                ['type' => 'text', 'text' => config('app.name', 'ZapReach')]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                Log::info("✅ WhatsApp message sent to {$phone}");
                return true;
            } else {
                $error = $response->json();
                Log::error("❌ WhatsApp send error:", array_merge($error, ['phone' => $phone]));
                return false;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("❌ WhatsApp send timeout/connection error:", ['error' => $e->getMessage(), 'phone' => $phone, 'type' => 'timeout']);
            return false;
        } catch (\Exception $e) {
            Log::error("❌ WhatsApp send error:", ['error' => $e->getMessage(), 'phone' => $phone]);
            return false;
        }
    }

    /**
     * Send WhatsApp message with template components
     */
    public function sendWhatsAppMessageSelected($phone, $templateName, $languageCode, $templateComponents, $variables = [], $headerVariables = [], $contact = null, $userId = null)
    {
        try {
            $settings = $this->resolveSettingsForUser($userId);

            if (!$settings) {
                Log::error('❌ No WhatsApp settings found in DB.', ['userId' => $userId]);
                throw new \Exception('No WhatsApp settings found for this user');
            }

            [$phoneNumberId, $accessToken] = $this->sendCredentials($settings, $userId);

            // Build dynamic components
            $processedComponents = $this->buildTemplateComponents($templateComponents, $variables, $headerVariables, $contact, $userId ?? $settings->userId);
            Log::info('processedComponents', ['processedComponents' => $processedComponents]);
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/v22.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'whatsapp_marketing_optin' => true,
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $languageCode],
                    'components' => $processedComponents
                ]
            ]);

            if ($response->successful()) {
                Log::info("✅ WhatsApp message sent to {$phone} using {$templateName}");
                Log::info($response->json() );
                return [
                    'success' => true,
                    'message_id' => $response->json()['messages'][0]['id'] ?? null
                ];
            } else {
                $error = $response->json();
                Log::error("❌ WhatsApp send error:", array_merge($error, ['phone' => $phone]));
                throw new \Exception(self::formatMetaErrorMessage($error['error'] ?? $error));
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("❌ WhatsApp send timeout/connection error:", ['error' => $e->getMessage(), 'phone' => $phone, 'type' => 'timeout']);
            throw new \Exception('WhatsApp API timeout: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("❌ WhatsApp send error:", ['error' => $e->getMessage(), 'phone' => $phone]);
            throw $e;
        }
    }

    /**
     * Send custom template message
     */
    public function sendCustomTemplate($phone, $templateName, $variables = [], $contact = null, $userId = null)
    {
        try {
            $settings = $this->resolveSettingsForUser($userId);

            if (!$settings) {
                Log::error('❌ No WhatsApp settings found in DB.', ['userId' => $userId]);
                return [
                    'success' => false,
                    'error' => 'No WhatsApp settings found for this user'
                ];
            }

            // Fetch template from DB
            Log::info("🔍 Looking for template: '{$templateName}'");

            $customTemplate = Template::where('name', $templateName)
                ->when($settings->userId, fn ($q) => $q->where('userId', $settings->userId))
                ->whereJsonContains('components', ['type' => 'BODY'])
                ->first();

            if (!$customTemplate) {
                $availableTemplates = Template::query()
                    ->when($settings->userId, fn ($q) => $q->where('userId', $settings->userId))
                    ->pluck('name')
                    ->toArray();
                return [
                    'success' => false,
                    'error' => "Template '{$templateName}' not found in database",
                    'available_templates' => $availableTemplates
                ];
            }

            [$phoneNumberId, $accessToken] = $this->sendCredentials($settings, $userId);

            // Validate & Replace Variables
            $messageContent = "";

            if ($customTemplate->components && is_array($customTemplate->components)) {
                $bodyComponent = collect($customTemplate->components)->firstWhere('type', 'BODY');

                if ($bodyComponent && isset($bodyComponent['text'])) {
                    $messageContent = trim($bodyComponent['text']);
                }
            }

            if (empty($messageContent)) {
                return [
                    'success' => false,
                    'error' => "Template '{$templateName}' has no BODY content"
                ];
            }

            // Replace variables
            if (is_string($variables)) {
                Log::info('dynamicvalue', ['value' => $variables]);
                $value = $variables ?: ($contact ? $this->getContactVariable($contact, 0) : "");
                $dynamicValue = $this->resolveVariable($value, $contact);
                $messageContent = preg_replace('/\{\{\s*1\s*\}\}/', $dynamicValue, $messageContent);
            } elseif (is_array($variables) && count($variables) > 0) {
                foreach ($variables as $index => $variable) {
                    $placeholder = '/\{\{\s*' . ($index + 1) . '\s*\}\}/';
                    $value = $variable ?: ($contact ? $this->getContactVariable($contact, $index) : "");
                    $dynamicValue = $this->resolveVariable($value, $contact);
                    $messageContent = preg_replace($placeholder, $dynamicValue, $messageContent);
                }
            }

            Log::info("✅ Final Processed Message: {$messageContent}");

            // WhatsApp API Call
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $messageContent]
            ]);

            if ($response->successful()) {
                Log::info("✅ Custom template message sent to {$phone}");
                return [
                    'success' => true,
                    'phone' => $phone,
                    'template_name' => $templateName,
                    'message_content' => $messageContent,
                    'message_id' => $response->json()['messages'][0]['id'] ?? null
                ];
            } else {
                $error = $response->json();
                Log::error("❌ Custom template send error:", array_merge($error, ['phone' => $phone, 'template_name' => $templateName]));
                return [
                    'success' => false,
                    'error' => self::formatMetaErrorMessage($error['error'] ?? $error),
                    'phone' => $phone,
                    'template_name' => $templateName
                ];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("❌ Custom template send timeout/connection error:", ['error' => $e->getMessage(), 'phone' => $phone, 'template_name' => $templateName, 'type' => 'timeout']);
            return [
                'success' => false,
                'error' => 'WhatsApp API timeout: ' . $e->getMessage(),
                'phone' => $phone,
                'template_name' => $templateName
            ];
        } catch (\Exception $e) {
            Log::error("❌ Custom template send error:", ['error' => $e->getMessage(), 'phone' => $phone, 'template_name' => $templateName]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'phone' => $phone,
                'template_name' => $templateName
            ];
        }
    }

    /**
     * Build template components
     */
    private function buildTemplateComponents($templateComponents, $variables = [], $headerVariables = [], $contact = null, $userId = null)
    {
        $components = [];
        // Short suffix (e.g. "r/1") to stay well under Meta's 15-character limit for URL buttons
        $redirectSuffix = $userId ? ('r/' . $userId) : 'r';

        foreach ($templateComponents as $component) {
            $processedComponent = ['type' => strtolower($component['type'])];

            switch (strtoupper($component['type'])) {
                case 'HEADER':
                    if (!isset($component['format'])) break;

                    $format = strtoupper($component['format']);
                    switch ($format) {
                        case 'IMAGE':
                            $imageUrl = $this->sanitizeMediaUrl($headerVariables);

                            Log::info('processedComponent', ['imageUrl' => $imageUrl, 'rawHeaderVariables' => $headerVariables]);

                            if (empty($imageUrl)) {
                                Log::error('❌ HEADER IMAGE URL invalid or missing', ['provided' => $headerVariables]);
                                break;
                            }

                            // Automatically convert WebP to JPG for WhatsApp Cloud API compatibility
                            $imageUrl = $this->ensureWhatsAppSupportedImage($imageUrl);

                            $processedComponent['parameters'] = [
                                [
                                    'type' => 'image',
                                    'image' => ['link' => $imageUrl]
                                ]
                            ];
                            break;

                        case 'DOCUMENT':
                            $docUrl = $this->sanitizeMediaUrl($component['text'] ?? $component['example']['document_url'] ?? $headerVariables);
                            if ($docUrl) {
                                $processedComponent['parameters'] = [
                                    [
                                        'type' => 'document',
                                        'document' => [
                                            'link' => $docUrl,
                                            'filename' => $component['filename'] ?? 'document.pdf'
                                        ]
                                    ]
                                ];
                            }
                            break;

                        case 'VIDEO':
                            $videoUrl = $this->sanitizeMediaUrl($headerVariables);

                            Log::info('processedComponent', ['videoUrl' => $videoUrl, 'rawHeaderVariables' => $headerVariables]);

                            if (empty($videoUrl)) {
                                Log::error('❌ HEADER VIDEO URL invalid or missing', ['provided' => $headerVariables]);
                                break;
                            }

                            $processedComponent['parameters'] = [
                                [
                                    'type' => 'video',
                                    'video' => ['link' => $videoUrl]
                                ]
                            ];
                            break;

                        default:
                            Log::info("Unknown HEADER format: {$format}");
                    }
                    break;

                case 'BODY':
                    if (isset($component['text'])) {
                        $paramCount = 0;
                        if (isset($component['example']['body_text'][0]) && is_array($component['example']['body_text'][0])) {
                            $paramCount = count($component['example']['body_text'][0]);
                        } else {
                            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $component['text'], $matches);
                            if (!empty($matches[1])) {
                                $paramCount = count(array_unique($matches[1]));
                            }
                        }

                        if ($paramCount > 0) {
                            $processedComponent['parameters'] = [];
                            for ($i = 0; $i < $paramCount; $i++) {
                                $value = $variables[$i] ?? ($contact ? $this->getContactVariable($contact, $i) : "");
                                $dynamicValue = $this->resolveVariable($value, $contact);

                                $processedComponent['parameters'][] = [
                                    'type' => 'text',
                                    'text' => (string) $dynamicValue
                                ];
                            }
                        }
                    }
                    break;

                case 'FOOTER':
                    // Meta Cloud API template footers are static and do not accept parameters
                    break;

                case 'BUTTONS':
                    if (isset($component['buttons']) && is_array($component['buttons'])) {
                        foreach ($component['buttons'] as $buttonIndex => $button) {
                            $buttonType = strtoupper((string) ($button['type'] ?? ''));

                            // Meta Cloud API: only include buttons that need runtime parameters.
                            // Static PHONE_NUMBER, QUICK_REPLY, and static URL buttons are part of
                            // the approved template and must NOT be sent (missing sub_type → error #100).
                            if ($buttonType === 'URL') {
                                $url = (string) ($button['url'] ?? '');
                                $hasDynamicSuffix = str_contains($url, '{{1}}') || str_contains($url, '{{ 1 }}');

                                // Static URL button — skip
                                if (! $hasDynamicSuffix) {
                                    continue;
                                }

                                $urlParam = $variables["button_{$buttonIndex}"] ?? $variables[count($variables) - 1] ?? '';
                                if (is_string($urlParam) && (str_contains($urlParam, 'redirectwhatsapp') || str_contains($urlParam, 'r/'))) {
                                    // If template button URL already includes 'redirectwhatsapp', send only the user ID as parameter (<= 15 chars)
                                    if (str_contains($url, 'redirectwhatsapp')) {
                                        $urlParam = $userId ? (string) $userId : '0';
                                    } else {
                                        $urlParam = $redirectSuffix;
                                    }
                                    Log::info('Button parameter redirect', ['userId' => $userId, 'suffix' => $urlParam, 'templateButtonUrl' => $url]);
                                }

                                $components[] = [
                                    'type' => 'button',
                                    'sub_type' => 'url',
                                    'index' => (string) $buttonIndex,
                                    'parameters' => [
                                        ['type' => 'text', 'text' => (string) $urlParam],
                                    ],
                                ];
                                continue;
                            }

                            if ($buttonType === 'COPY_CODE') {
                                $code = $variables["button_{$buttonIndex}"]
                                    ?? $variables['coupon_code']
                                    ?? $button['example']
                                    ?? '';
                                if ($code === '' || $code === null) {
                                    continue;
                                }

                                $components[] = [
                                    'type' => 'button',
                                    'sub_type' => 'copy_code',
                                    'index' => (string) $buttonIndex,
                                    'parameters' => [
                                        ['type' => 'coupon_code', 'coupon_code' => (string) $code],
                                    ],
                                ];
                                continue;
                            }

                            // PHONE_NUMBER, QUICK_REPLY, CATALOG, MPM, etc. — static, do not send
                            Log::info('Skipping static template button in send payload', [
                                'index' => $buttonIndex,
                                'type' => $buttonType,
                                'text' => $button['text'] ?? null,
                            ]);
                        }
                    }
                    break;

                default:
                    // Log::info("Unknown component type: {$component['type']}");
            }

            if (!in_array(strtoupper($component['type']), ['BUTTONS', 'FOOTER'], true)) {
                $components[] = $processedComponent;
            }
        }

        return $components;
    }

    /**
     * Sanitize and format media URL for WhatsApp Cloud API
     */
    private function sanitizeMediaUrl($url): ?string
    {
        if (empty($url)) return null;

        if (is_array($url)) {
            $first = reset($url);
            $url = is_array($first) ? reset($first) : $first;
        }

        if (!is_string($url)) return null;

        $url = trim($url);
        if ($url === '') return null;

        // Convert relative path to full absolute URL if needed
        if (!preg_match('~^https?://~i', $url)) {
            $url = ltrim($url, '/');
            $baseUrl = rtrim(config('app.url'), '/');
            $url = $baseUrl . '/' . $url;
        }

        // Parse and properly encode spaces and special characters in path
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        // Raw urlencode path segments while leaving slashes
        $rawSegments = explode('/', $path);
        $encodedSegments = array_map(function ($segment) {
            return rawurlencode(rawurldecode($segment));
        }, $rawSegments);
        $encodedPath = implode('/', $encodedSegments);

        $cleanUrl = "{$scheme}://{$host}{$port}{$encodedPath}{$query}";

        if (!filter_var($cleanUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $cleanUrl;
    }

    /**
     * WhatsApp Cloud API does not support WebP images in template headers or media messages.
     * Automatically converts .webp images to .jpg on the fly, saving to public/uploads/media.
     * Preserves transparency by using a white background.
     */
    public function ensureWhatsAppSupportedImage(?string $url): ?string
    {
        if (empty($url) || !is_string($url)) {
            return $url;
        }

        $parsedPath = parse_url($url, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));

        if ($extension !== 'webp') {
            return $url;
        }

        try {
            $filenameWithoutExt = pathinfo($parsedPath, PATHINFO_FILENAME);
            if (empty($filenameWithoutExt)) {
                $filenameWithoutExt = 'converted_' . md5($url);
            }
            $targetJpgFilename = $filenameWithoutExt . '.jpg';

            $mediaDir = public_path('uploads/media');
            if (!file_exists($mediaDir)) {
                @mkdir($mediaDir, 0755, true);
            }

            $targetJpgPath = $mediaDir . DIRECTORY_SEPARATOR . $targetJpgFilename;

            // If the converted JPG already exists and is not empty, use it directly
            if (!file_exists($targetJpgPath) || filesize($targetJpgPath) === 0) {
                // Locate source WebP image
                $sourceWebpPath = null;
                $originalFilename = basename($parsedPath);

                $possiblePaths = [
                    $mediaDir . DIRECTORY_SEPARATOR . $originalFilename,
                    public_path(ltrim($parsedPath, '/\\')),
                    public_path(ltrim(preg_replace('#^/?public/#', '/', $parsedPath), '/\\')),
                ];

                foreach ($possiblePaths as $cand) {
                    if (file_exists($cand) && is_file($cand)) {
                        $sourceWebpPath = $cand;
                        break;
                    }
                }

                $gdImage = null;
                if ($sourceWebpPath && function_exists('imagecreatefromwebp')) {
                    $gdImage = @imagecreatefromwebp($sourceWebpPath);
                }

                // If not found locally, fetch remotely
                if (!$gdImage) {
                    $content = @file_get_contents($url, false, stream_context_create([
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                        'http' => ['timeout' => 15],
                    ]));

                    if ($content !== false && !empty($content)) {
                        if (function_exists('imagecreatefromstring')) {
                            $gdImage = @imagecreatefromstring($content);
                        }
                        if (!$gdImage && function_exists('imagecreatefromwebp')) {
                            $tempWebp = tempnam(sys_get_temp_dir(), 'wbp_');
                            file_put_contents($tempWebp, $content);
                            $gdImage = @imagecreatefromwebp($tempWebp);
                            @unlink($tempWebp);
                        }
                    }
                }

                if (!$gdImage) {
                    Log::warning('⚠️ Could not load WebP image for conversion', ['url' => $url]);
                    return $url;
                }

                $width = imagesx($gdImage);
                $height = imagesy($gdImage);

                // Create truecolor canvas with white background (avoids black background on transparent WebP)
                $canvas = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
                imagecopy($canvas, $gdImage, 0, 0, 0, 0, $width, $height);

                imagejpeg($canvas, $targetJpgPath, 90);

                imagedestroy($canvas);
                imagedestroy($gdImage);

                Log::info('✅ Converted WebP to JPG for WhatsApp API compatibility', [
                    'original' => $url,
                    'converted_file' => $targetJpgPath,
                ]);
            }

            // Determine the URL for the converted JPG
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            $urlHost = parse_url($url, PHP_URL_HOST);
            $isProduction = app()->isProduction();

            // If the original URL was from this application or a relative path, simply replace extension
            if (!$urlHost || ($appHost && strcasecmp($urlHost, $appHost) === 0)) {
                return preg_replace('/\.webp(\?.*)?$/i', '.jpg$1', $url);
            }

            // If external URL, point to our local converted file
            $storedPrefix = $isProduction ? '/public/uploads/media/' : '/uploads/media/';
            return rtrim(config('app.url'), '/') . $storedPrefix . $targetJpgFilename;

        } catch (\Throwable $e) {
            Log::warning('⚠️ Error during WebP to JPG conversion: ' . $e->getMessage(), [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);
            return $url;
        }
    }

    /**
     * Resolve variable values
     */
    private function resolveVariable($value, $contact)
    {
        if (!is_string($value)) return $value;

        if (str_starts_with($value, 'contact::')) {
            $key = explode('::', $value)[1];
            if ($contact && isset($contact[$key])) {
                return $contact[$key];
            } else {
                Log::warning("⚠️ Contact key '{$key}' not found in contact object");
                return "";
            }
        }
        return $value;
    }

    /**
     * Replace variables in text
     */
    private function replaceVariables($text, $variables, $contact)
    {
        $processedText = $text;

        if (is_array($variables)) {
            foreach ($variables as $index => $variable) {
                $placeholder = '/\{\{\s*' . ($index + 1) . '\s*\}\}/';
                $processedText = preg_replace($placeholder, $variable, $processedText);
            }
        }

        if ($contact) {
            $processedText = str_replace('{{name}}', $contact['name'] ?? $contact['firstName'] ?? '', $processedText);
            $processedText = str_replace('{{phone}}', $contact['phone'] ?? '', $processedText);
            $processedText = str_replace('{{email}}', $contact['email'] ?? '', $processedText);
        }

        return $processedText;
    }

    /**
     * Get contact variable by index
     */
    private function getContactVariable($contact, $index)
    {
        $contactFields = [
            $contact['name'] ?? '',
            $contact['firstName'] ?? '',
            $contact['phone'] ?? '',
            $contact['email'] ?? '',
            $contact['company'] ?? ''
        ];
        return $contactFields[$index] ?? "";
    }

    /**
     * Send free-form message (not template-based)
     */
    public function sendFreeFormMessage($phone, $message, $type = 'text', $mediaUrl = null, $mediaType = null, $mediaFilename = null, $userId = null)
    {
        try {
            $settings = $this->resolveSettingsForUser($userId);

            if (!$settings) {
                Log::error('❌ No WhatsApp settings found in DB.', ['userId' => $userId]);
                return [
                    'success' => false,
                    'error' => 'No WhatsApp settings found for this user'
                ];
            }

            [$phoneNumberId, $accessToken] = $this->sendCredentials($settings, $userId);

            // Prepare message payload based on type
            $messagePayload = $this->buildFreeFormMessagePayload($type, $message, $mediaUrl, $mediaType, $mediaFilename);

            if (!$messagePayload) {
                return [
                    'success' => false,
                    'error' => 'Invalid message type or missing required parameters'
                ];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => $type,
                $type => $messagePayload
            ]);

            if ($response->successful()) {
                Log::info("✅ Free-form message sent to {$phone}");
                return [
                    'success' => true,
                    'message_id' => $response->json()['messages'][0]['id'] ?? null,
                    'phone' => $phone,
                    'type' => $type
                ];
            } else {
                $error = $response->json();
                Log::error("❌ Free-form message send error:", array_merge($error, ['phone' => $phone]));
                return [
                    'success' => false,
                    'error' => self::formatMetaErrorMessage($error['error'] ?? $error)
                ];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("❌ Free-form message send timeout/connection error:", ['error' => $e->getMessage(), 'phone' => $phone, 'type' => 'timeout']);
            return [
                'success' => false,
                'error' => 'WhatsApp API timeout: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error("❌ Free-form message send error:", ['error' => $e->getMessage(), 'phone' => $phone]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Download WhatsApp media using media ID
     */
    public function downloadWhatsAppMedia($mediaId)
    {
        try {
            $settings = Settings::where('isActive', true)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$settings) {
                Log::error('❌ No WhatsApp settings found in DB.');
                return [
                    'success' => false,
                    'error' => 'No WhatsApp settings found'
                ];
            }

            $accessToken = $settings->access_token;

            // First, get the media URL from WhatsApp API
            $mediaResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/v21.0/{$mediaId}");

            if (!$mediaResponse->successful()) {
                Log::error("❌ Failed to get media info from WhatsApp API:", $mediaResponse->json());
                return [
                    'success' => false,
                    'error' => 'Failed to get media info from WhatsApp API'
                ];
            }

            $mediaData = $mediaResponse->json();
            $mediaUrl = $mediaData['url'] ?? null;

            if (!$mediaUrl) {
                return [
                    'success' => false,
                    'error' => 'Media URL not found in WhatsApp response'
                ];
            }

            // Download the media file
            $mediaContent = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get($mediaUrl);

            if (!$mediaContent->successful()) {
                Log::error("❌ Failed to download media from WhatsApp:", $mediaContent->body());
                return [
                    'success' => false,
                    'error' => 'Failed to download media from WhatsApp'
                ];
            }

            // Generate filename and save to storage
            $mimeType = $mediaData['mime_type'] ?? 'application/octet-stream';
            $extension = $this->getExtensionFromMimeType($mimeType);
            $filename = time() . '_' . $mediaId . '.' . $extension;

            // Create media directory if it doesn't exist
            $mediaPath = public_path('uploads/media');
            if (!file_exists($mediaPath)) {
                mkdir($mediaPath, 0755, true);
            }

            // Save the file
            $filePath = $mediaPath . '/' . $filename;
            file_put_contents($filePath, $mediaContent->body());

            // Determine the stored path based on environment
            $isProduction = app()->isProduction();
            $storedPath = $isProduction
                ? '/public/uploads/media/' . $filename
                : '/uploads/media/' . $filename;

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $storedPath,
                'url' => $storedPath,
                'mime_type' => $mimeType,
                'size' => strlen($mediaContent->body()),
                'media_id' => $mediaId
            ];

        } catch (\Exception $e) {
            Log::error("❌ WhatsApp media download error:", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file extension from MIME type
     */
    private function getExtensionFromMimeType($mimeType)
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/avi' => 'avi',
            'video/mov' => 'mov',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt'
        ];

        return $mimeToExt[$mimeType] ?? 'bin';
    }

    /**
     * Build message payload for different message types
     */
    private function buildFreeFormMessagePayload($type, $message, $mediaUrl = null, $mediaType = null, $mediaFilename = null)
    {
        switch ($type) {
            case 'text':
                return ['body' => $message];

            case 'image':
                if (!$mediaUrl) return null;
                $mediaUrl = $this->ensureWhatsAppSupportedImage($mediaUrl);
                return [
                    'link' => $mediaUrl,
                    'caption' => $message
                ];

            case 'video':
                if (!$mediaUrl) return null;
                return [
                    'link' => $mediaUrl,
                    'caption' => $message
                ];

            case 'audio':
                if (!$mediaUrl) return null;
                return [
                    'link' => $mediaUrl
                ];

            case 'document':
                if (!$mediaUrl) return null;
                return [
                    'link' => $mediaUrl,
                    'filename' => $mediaFilename ?: 'document',
                    'caption' => $message
                ];

            case 'location':
                // For location, message should contain lat,lng format
                $coords = explode(',', $message);
                if (count($coords) !== 2) return null;
                return [
                    'latitude' => floatval(trim($coords[0])),
                    'longitude' => floatval(trim($coords[1])),
                    'name' => $mediaFilename ?: 'Location',
                    'address' => $message
                ];

            case 'contact':
                // For contact, message should contain phone number
                return [
                    'contacts' => [
                        [
                            'name' => [
                                'formatted_name' => $mediaFilename ?: 'Contact'
                            ],
                            'phones' => [
                                [
                                    'phone' => $message,
                                    'type' => 'MOBILE'
                                ]
                            ]
                        ]
                    ]
                ];

            default:
                return null;
        }
    }

    /**
     * Convert technical Meta Graph API error payloads into clear, actionable messages for users.
     */
    public static function formatMetaErrorMessage($error): string
    {
        if (is_array($error) && isset($error[0]) && is_array($error[0])) {
            $error = $error[0];
        }

        if (is_array($error)) {
            $code = $error['code'] ?? null;
            $rawTitle = (string) ($error['title'] ?? '');
            $rawMsg = (string) ($error['message'] ?? '');
            $errorData = (string) ($error['error_data']['details'] ?? '');
        } else {
            $code = null;
            $rawTitle = '';
            $rawMsg = (string) $error;
            $errorData = '';
        }

        $lower = strtolower($rawTitle . ' ' . $rawMsg . ' ' . $errorData);

        // 1. Authentication / Expired Token (Code 190 / OAuthException)
        if ($code == 190 || str_contains($lower, 'authentication error') || str_contains($lower, 'access token') || str_contains($lower, 'session has expired') || str_contains($lower, 'validating access token')) {
            return 'WhatsApp Access Token is expired or invalid. Please update your WhatsApp Access Token with a permanent System User Token in Settings.';
        }

        // 2. Healthy Ecosystem Engagement / Meta Frequency Capping (Code 131049, 131045)
        if ($code == 131049 || $code == 131045 || str_contains($lower, 'ecosystem engagement') || str_contains($lower, 'healthy ecosystem')) {
            return 'Delivery blocked by Meta: Message not delivered to maintain healthy ecosystem engagement (Meta limits marketing spam/frequency or recipient has not engaged recently).';
        }

        // 3. 24-hour messaging window (Code 131047 / Re-engagement)
        if ($code == 131047 || str_contains($lower, '24 hours') || str_contains($lower, 're-engagement') || str_contains($lower, 'customer last replied')) {
            return 'The 24-hour customer messaging window has closed. You can only send an approved Template message to re-engage this customer.';
        }

        // 4. Sender WhatsApp Business Phone Number not registered with Cloud API (Code 133010)
        if ($code == 133010 || str_contains($lower, 'account not registered')) {
            return 'Your WhatsApp Business sending phone number is not registered on Meta WhatsApp Cloud API (Error #133010). Please complete the 2-step verification PIN registration in Meta WhatsApp Manager or check your Phone Number ID in Settings.';
        }

        // 5. Recipient cannot receive messages / phone not registered (Code 131026, 131000, 131021)
        if ($code == 131026 || $code == 131000 || $code == 131021 || str_contains($lower, 'not a valid whatsapp user') || str_contains($lower, 'receiver is incapable') || str_contains($lower, 'recipient phone number is not registered')) {
            return 'Recipient phone number is not registered on WhatsApp or cannot receive WhatsApp messages.';
        }

        // 5. User opted out / blocked (Code 131053)
        if ($code == 131053 || str_contains($lower, 'opted out') || str_contains($lower, 'blocked')) {
            return 'Recipient has opted out or blocked messages from this WhatsApp Business Account.';
        }

        // 6. Unsupported message type (Code 131051)
        if ($code == 131051 || str_contains($lower, 'message type unknown') || str_contains($lower, 'not supported')) {
            return 'Message type is not supported by the recipient\'s WhatsApp application.';
        }

        // 7. Rate limits (Code 130429, 131048, 131056)
        if ($code == 130429 || $code == 131048 || $code == 131056 || str_contains($lower, 'rate limit') || str_contains($lower, 'too many requests')) {
            return 'WhatsApp rate limit reached. Please wait a moment before sending more messages.';
        }

        // 8. Payment issue / WABA suspended / Unsettled payments (Code 131031, 131042)
        if ($code == 131031 || $code == 131042 || str_contains($lower, 'payment') || str_contains($lower, 'payment issue') || str_contains($lower, 'unsettled')) {
            if (!empty($errorData)) {
                return "Meta WhatsApp Payment Issue: {$errorData}";
            }
            return 'WhatsApp account payment issue: Your WhatsApp Business account has unsettled payments with Meta (Facebook). Please visit Meta Business Billing Hub to pay your bill.';
        }

        // 9. Template not found / paused / rejected (Code 132000, 132001)
        if ($code == 132000 || $code == 132001 || str_contains($lower, 'template does not exist') || str_contains($lower, 'template paused') || str_contains($lower, 'template rejected')) {
            return 'The requested WhatsApp template does not exist or has been paused/rejected in Meta Business Manager.';
        }

        // 10. General fallback with detail if available
        if (!empty($errorData)) {
            return $errorData;
        }

        if (!empty($rawMsg)) {
            return $rawMsg;
        }

        if (!empty($rawTitle)) {
            return $rawTitle;
        }

        return 'WhatsApp delivery error. Please verify your settings and recipient phone number.';
    }
}
