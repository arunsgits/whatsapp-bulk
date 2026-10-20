<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendChatMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int    $userId,          // The WhatsApp account owner (receiver of notification)
        public int    $conversationId,
        public string $senderName,      // Contact's name or phone number
        public string $phoneNumber,     // Customer phone number
        public string $autoAiEnabled,   // '1' or '0'
        public string $messagePreview,  // First 100 chars of message
        public string $messageType      // text, image, video, document, etc.
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning("[ChatNotify] User #{$this->userId} not found. Skipping.");
            return;
        }

        $fcmToken = $user->fcm_token ?? null;

        if (empty($fcmToken)) {
            Log::info("[ChatNotify] User #{$this->userId} ({$user->name}) has no FCM token. Skipping.");
            return;
        }

        // Build notification body based on message type
        $body = match ($this->messageType) {
            'image'    => '📷 ' . ($this->messagePreview ?: 'Image'),
            'video'    => '🎥 ' . ($this->messagePreview ?: 'Video'),
            'audio'    => '🎵 Voice message',
            'document' => '📄 ' . ($this->messagePreview ?: 'Document'),
            'sticker'  => '🎉 Sticker',
            'location' => '📍 Location',
            default    => $this->messagePreview ?: 'New message',
        };

        try {
            $accessToken = $this->getAccessToken();
            $projectId   = config('services.fcm.project_id');

            $payload = [
                'message' => [
                    'token'        => $fcmToken,
                    'notification' => [
                        'title' => $this->senderName,
                        'body'  => $body,
                    ],
                    'data' => [
                        'conversation_id' => (string) $this->conversationId,
                        'contact_name'    => (string) $this->senderName,
                        'phone_number'    => (string) $this->phoneNumber,
                        'auto_ai_enabled' => (string) $this->autoAiEnabled,
                        'type'            => 'chat',
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound'      => 'default',
                            'channel_id' => 'basic_channel',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound'             => 'default',
                                'badge'             => 1,
                                'content-available' => 1,
                            ],
                        ],
                    ],
                ],
            ];

            $response     = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);
            $responseBody = $response->json();

            if ($response->successful() && isset($responseBody['name'])) {
                Log::info("[ChatNotify] ✅ Pushed to User #{$this->userId} ({$user->name}) for conv #{$this->conversationId}. FCM: {$responseBody['name']}");
            } else {
                $errorCode    = $responseBody['error']['status'] ?? 'UNKNOWN';
                $errorMessage = $responseBody['error']['message'] ?? 'Unknown FCM error';
                Log::error("[ChatNotify] ❌ FCM failed for User #{$this->userId}: [{$errorCode}] {$errorMessage}");

                // Clear invalid/expired tokens automatically
                if (in_array($errorCode, ['INVALID_ARGUMENT', 'UNREGISTERED', 'NOT_FOUND'])) {
                    Log::warning("[ChatNotify] Clearing invalid FCM token for User #{$this->userId}");
                    \DB::table('users')->where('id', $this->userId)->update(['fcm_token' => null]);
                }
            }
        } catch (\Exception $e) {
            Log::error("[ChatNotify] Exception for User #{$this->userId}: " . $e->getMessage());
            throw $e; // Let queue retry
        }
    }

    /**
     * Get a short-lived OAuth2 access token using the Firebase service account JSON.
     * Same approach as SendPlanExpiryNotification.
     */
    private function getAccessToken(): string
    {
        $credentialsPath = base_path(config('services.fcm.credentials_path'));

        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException("Firebase credentials file not found at: {$credentialsPath}");
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        $now     = time();
        $header  = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $signInput = "{$header}.{$payload}";
        openssl_sign($signInput, $signature, $credentials['private_key'], 'SHA256');
        $signedJwt = "{$signInput}." . base64_encode($signature);

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $signedJwt,
        ]);

        if (!$tokenResponse->successful()) {
            throw new \RuntimeException('Failed to obtain FCM access token: ' . $tokenResponse->body());
        }

        return $tokenResponse->json('access_token');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[ChatNotify] Job permanently failed for User #{$this->userId}: " . $exception->getMessage());
    }
}
