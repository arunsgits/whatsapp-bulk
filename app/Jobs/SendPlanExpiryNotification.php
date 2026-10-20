<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserPackageHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPlanExpiryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public int $historyId,
        public string $planName,
        public string $endDate
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning("[PlanExpiry] User #{$this->userId} not found. Skipping.");
            return;
        }

        $fcmToken = $user->fcm_token ?? null;

        if (empty($fcmToken)) {
            Log::info("[PlanExpiry] User #{$this->userId} ({$user->name}) has no FCM token. Skipping.");
            $this->markNotified();
            return;
        }

        try {
            $accessToken = $this->getAccessToken();
            $projectId   = config('services.fcm.project_id');

            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => '⚠️ Plan Expiring Soon',
                        'body'  => "Your {$this->planName} plan expires on {$this->endDate}. Renew now to avoid service interruption.",
                    ],
                    'data' => [
                        'type'     => 'plan_expiry_reminder',
                        'userId'   => (string) $this->userId,
                        'planName' => $this->planName,
                        'endDate'  => $this->endDate,
                        'daysLeft' => '6',
                    ],
                    'android' => [
                        'notification' => [
                            'sound'        => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => ['sound' => 'default'],
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['name'])) {
                Log::info("[PlanExpiry] ✅ Sent to User #{$this->userId} ({$user->name}). Message: {$body['name']}");
                $this->markNotified();
            } else {
                $errorCode    = $body['error']['status'] ?? 'UNKNOWN';
                $errorMessage = $body['error']['message'] ?? 'Unknown FCM error';
                Log::error("[PlanExpiry] ❌ FCM failed for User #{$this->userId}: [{$errorCode}] {$errorMessage}");

                // Clear invalid tokens
                if (in_array($errorCode, ['INVALID_ARGUMENT', 'UNREGISTERED', 'NOT_FOUND'])) {
                    Log::warning("[PlanExpiry] Clearing invalid FCM token for User #{$this->userId}");
                    \DB::table('users')->where('id', $this->userId)->update(['fcm_token' => null]);
                    $this->markNotified();
                }
            }
        } catch (\Exception $e) {
            Log::error("[PlanExpiry] Exception for User #{$this->userId}: " . $e->getMessage());
            throw $e; // Let queue retry
        }
    }

    /**
     * Get a short-lived OAuth2 access token using the Firebase service account JSON.
     */
    private function getAccessToken(): string
    {
        $credentialsPath = base_path(config('services.fcm.credentials_path'));

        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException("Firebase credentials file not found at: {$credentialsPath}");
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
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

    private function markNotified(): void
    {
        UserPackageHistory::where('id', $this->historyId)->update(['notified_6day' => true]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[PlanExpiry] Job permanently failed for User #{$this->userId}: " . $exception->getMessage());
    }
}
