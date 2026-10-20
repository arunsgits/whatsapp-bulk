<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWabaSubscribeService
{
    /**
     * Subscribe the Meta app (tied to the access token) to this WABA
     * so production message webhooks are delivered.
     *
     * Graph: POST /{waba-id}/subscribed_apps
     */
    public function subscribe(?Settings $settings): array
    {
        if (! $settings) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No WhatsApp settings found',
            ];
        }

        $wabaId = trim((string) ($settings->waba_id ?? ''));
        $accessToken = trim((string) ($settings->access_token ?? ''));

        if ($wabaId === '' || $accessToken === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'WABA ID and access token are required to subscribe',
                'userId' => $settings->userId,
            ];
        }

        try {
            $response = Http::timeout(20)
                ->withToken($accessToken)
                ->post("https://graph.facebook.com/v21.0/{$wabaId}/subscribed_apps");

            $body = $response->json() ?? [];
            $ok = $response->successful() && (($body['success'] ?? false) === true || $response->status() === 200);

            if (! $ok) {
                $message = $body['error']['message'] ?? $response->body();
                Log::warning('Meta WABA subscribe failed', [
                    'userId' => $settings->userId,
                    'waba_id' => $wabaId,
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                return [
                    'success' => false,
                    'skipped' => false,
                    'message' => is_string($message) ? $message : 'Meta subscribe failed',
                    'userId' => $settings->userId,
                    'waba_id' => $wabaId,
                    'status' => $response->status(),
                    'meta' => $body,
                ];
            }

            $subscribed = $this->listSubscribedApps($wabaId, $accessToken);

            Log::info('Meta WABA app subscribed', [
                'userId' => $settings->userId,
                'waba_id' => $wabaId,
                'subscribed_apps' => $subscribed,
            ]);

            return [
                'success' => true,
                'skipped' => false,
                'message' => 'WhatsApp app subscribed to WABA successfully',
                'userId' => $settings->userId,
                'waba_id' => $wabaId,
                'subscribed_apps' => $subscribed,
            ];
        } catch (\Throwable $e) {
            Log::error('Meta WABA subscribe error: ' . $e->getMessage(), [
                'userId' => $settings->userId,
                'waba_id' => $wabaId,
            ]);

            return [
                'success' => false,
                'skipped' => false,
                'message' => $e->getMessage(),
                'userId' => $settings->userId,
                'waba_id' => $wabaId,
            ];
        }
    }

    /**
     * Subscribe using explicit credentials (before/without a Settings row).
     */
    public function subscribeWithCredentials(string $wabaId, string $accessToken, $userId = null): array
    {
        $settings = new Settings([
            'userId' => $userId,
            'waba_id' => $wabaId,
            'access_token' => $accessToken,
        ]);

        return $this->subscribe($settings);
    }

    public function listSubscribedApps(string $wabaId, string $accessToken): array
    {
        try {
            $response = Http::timeout(15)
                ->withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$wabaId}/subscribed_apps");

            if (! $response->successful()) {
                return [];
            }

            return $response->json('data') ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
