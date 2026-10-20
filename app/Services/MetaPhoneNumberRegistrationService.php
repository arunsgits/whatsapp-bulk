<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaPhoneNumberRegistrationService
{
    /**
     * Register a WhatsApp Business phone number on Meta Cloud API with a 6-digit PIN.
     *
     * Meta API: POST https://graph.facebook.com/v22.0/{phone-number-id}/register
     * Payload: { "messaging_product": "whatsapp", "pin": "123456" }
     *
     * @param string $phoneNumberId Meta Phone Number ID (NOT WABA ID)
     * @param string $accessToken System User Permanent Access Token
     * @param string $pin 6-digit two-step verification PIN
     * @param int|null $userId Optional user ID for logging / context
     * @return array
     */
    public function register(string $phoneNumberId, string $accessToken, string $pin, ?int $userId = null): array
    {
        $phoneNumberId = trim($phoneNumberId);
        $accessToken = trim($accessToken);
        $pin = trim($pin);

        if ($phoneNumberId === '') {
            return [
                'success' => false,
                'message' => 'Phone Number ID is required to register with Meta Cloud API.',
            ];
        }

        if ($accessToken === '') {
            return [
                'success' => false,
                'message' => 'WhatsApp Access Token is required.',
            ];
        }

        if (!preg_match('/^\d{6}$/', $pin)) {
            return [
                'success' => false,
                'message' => 'Two-step verification PIN must be exactly 6 digits.',
            ];
        }

        try {
            Log::info('Registering WhatsApp Phone Number on Meta Cloud API', [
                'userId' => $userId,
                'phoneNumberId' => $phoneNumberId,
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("https://graph.facebook.com/v22.0/{$phoneNumberId}/register", [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
            ]);

            $body = $response->json() ?? [];
            $isSuccess = $response->successful() && (($body['success'] ?? false) === true || $response->status() === 200);

            if ($isSuccess) {
                Log::info("✅ WhatsApp Phone Number {$phoneNumberId} registered successfully on Meta", [
                    'userId' => $userId,
                    'response' => $body,
                ]);

                // Query live status from Meta to return verified phone details
                $statusData = $this->getPhoneStatus($phoneNumberId, $accessToken);

                return [
                    'success' => true,
                    'message' => 'WhatsApp Phone Number registered and connected successfully on Meta Cloud API.',
                    'phone_number_id' => $phoneNumberId,
                    'meta_response' => $body,
                    'phone_status' => $statusData['data'] ?? null,
                ];
            }

            // Error handling from Meta
            $error = $body['error'] ?? [];
            $errorMessage = $this->formatRegistrationError($error, $response->body());

            Log::error("❌ Failed to register WhatsApp Phone Number {$phoneNumberId} on Meta", [
                'userId' => $userId,
                'status' => $response->status(),
                'error' => $error,
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $error['code'] ?? $response->status(),
                'error_subcode' => $error['error_subcode'] ?? null,
                'meta_error' => $error,
            ];
        } catch (\Throwable $e) {
            Log::error("❌ Exception during WhatsApp Phone Number registration: " . $e->getMessage(), [
                'userId' => $userId,
                'phoneNumberId' => $phoneNumberId,
            ]);

            return [
                'success' => false,
                'message' => 'Connection error while communicating with Meta: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Query live registration & quality status of a WhatsApp Phone Number from Meta.
     *
     * Meta API: GET https://graph.facebook.com/v22.0/{phone-number-id}?fields=display_phone_number,verified_name,code_verification_status,quality_rating,name_status,status,throughput
     */
    public function getPhoneStatus(string $phoneNumberId, string $accessToken): array
    {
        $phoneNumberId = trim($phoneNumberId);
        $accessToken = trim($accessToken);

        if ($phoneNumberId === '' || $accessToken === '') {
            return [
                'success' => false,
                'message' => 'Phone Number ID and Access Token are required.',
            ];
        }

        try {
            $response = Http::timeout(15)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://graph.facebook.com/v22.0/{$phoneNumberId}", [
                'fields' => 'display_phone_number,verified_name,code_verification_status,quality_rating,name_status,status,throughput',
            ]);

            $body = $response->json() ?? [];

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => [
                        'phone_number_id' => $phoneNumberId,
                        'display_phone_number' => $body['display_phone_number'] ?? null,
                        'verified_name' => $body['verified_name'] ?? null,
                        'code_verification_status' => $body['code_verification_status'] ?? null,
                        'status' => $body['status'] ?? null,
                        'quality_rating' => $body['quality_rating'] ?? null,
                        'name_status' => $body['name_status'] ?? null,
                        'throughput' => $body['throughput'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => $body['error']['message'] ?? 'Failed to query phone number status from Meta',
                'meta_error' => $body['error'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Deregister a phone number from Cloud API (if needed).
     *
     * Meta API: POST https://graph.facebook.com/v22.0/{phone-number-id}/deregister
     */
    public function deregister(string $phoneNumberId, string $accessToken, ?string $pin = null): array
    {
        $phoneNumberId = trim($phoneNumberId);
        $accessToken = trim($accessToken);

        if ($phoneNumberId === '' || $accessToken === '') {
            return [
                'success' => false,
                'message' => 'Phone Number ID and Access Token are required.',
            ];
        }

        try {
            $payload = [];
            if (!empty($pin)) {
                $payload['pin'] = $pin;
            }

            $response = Http::timeout(20)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("https://graph.facebook.com/v22.0/{$phoneNumberId}/deregister", $payload);

            $body = $response->json() ?? [];

            if ($response->successful() && (($body['success'] ?? false) === true || $response->status() === 200)) {
                return [
                    'success' => true,
                    'message' => 'Phone number deregistered successfully from Cloud API.',
                    'meta_response' => $body,
                ];
            }

            return [
                'success' => false,
                'message' => $body['error']['message'] ?? 'Failed to deregister phone number.',
                'meta_error' => $body['error'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format Meta registration error into actionable instructions.
     */
    private function formatRegistrationError(array $error, string $rawBody): string
    {
        $code = $error['code'] ?? null;
        $subcode = $error['error_subcode'] ?? null;
        $msg = $error['message'] ?? '';
        $userMsg = $error['error_user_msg'] ?? '';

        if (!empty($userMsg)) {
            return $userMsg;
        }

        if ($code == 100 && str_contains(strtolower($msg), 'pin')) {
            return 'Invalid two-step verification PIN. Please provide a valid 6-digit PIN.';
        }

        if ($code == 133016) {
            return 'Two-step verification PIN is incorrect. Please provide the exact 6-digit PIN previously configured for this phone number.';
        }

        if ($code == 133015) {
            return 'Phone number registration is rate-limited. Too many PIN attempts. Please wait a few minutes before trying again.';
        }

        if ($code == 133005) {
            return 'Two-step verification PIN mismatch. Please check your PIN or reset it in Meta WhatsApp Manager.';
        }

        if ($code == 133010) {
            return 'Account not registered. Please ensure this phone number is verified in WhatsApp Manager before registering.';
        }

        if ($code == 190) {
            return 'WhatsApp Access Token is expired or invalid. Please check your System User Token in Settings.';
        }

        return !empty($msg) ? $msg : ($rawBody ?: 'Meta registration failed');
    }
}
