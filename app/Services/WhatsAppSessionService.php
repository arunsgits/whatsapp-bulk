<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppSessionService
{
    protected string $baseUrl;
    protected string $secretKey;
    protected int $timeout;
    protected ?string $socketPath;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.whatsapp_session.url', env('WHATSAPP_SESSION_URL', 'http://127.0.0.1:3001')), '/');
        $this->secretKey = config('services.whatsapp_session.secret', env('WHATSAPP_SESSION_SECRET', 'wa_session_secret_key_2026'));
        $this->timeout = (int) env('WHATSAPP_SESSION_TIMEOUT', 15);
        $this->socketPath = env('WHATSAPP_SESSION_SOCKET_PATH', null);
    }

    /**
     * Check if the WhatsApp session bridge service is online.
     */
    public function isServiceOnline(): bool
    {
        try {
            $response = Http::timeout(3)
                ->withHeaders(['x-session-secret' => $this->secretKey])
                ->get("{$this->baseUrl}/health");

            return $response->successful() && ($response->json('status') === 'ok');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Initialize a WhatsApp session by ID.
     */
    public function initSession(string $sessionId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-session-secret' => $this->secretKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/sessions/init", [
                    'session_id' => $sessionId,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => 'error',
                'message' => $response->json('message') ?? 'Failed to initialize WhatsApp session',
            ];
        } catch (Exception $e) {
            Log::error("WhatsAppSessionService initSession error: {$e->getMessage()}", ['session_id' => $sessionId]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get session connection status and QR code if ready.
     */
    public function getSessionStatus(string $sessionId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['x-session-secret' => $this->secretKey])
                ->get("{$this->baseUrl}/sessions/{$sessionId}/status");

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => 'disconnected',
                'is_connected' => false,
            ];
        } catch (Exception $e) {
            Log::error("WhatsAppSessionService getSessionStatus error: {$e->getMessage()}", ['session_id' => $sessionId]);
            return [
                'status' => 'disconnected',
                'is_connected' => false,
            ];
        }
    }

    /**
     * Delete or logout a WhatsApp session.
     */
    public function deleteSession(string $sessionId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['x-session-secret' => $this->secretKey])
                ->delete("{$this->baseUrl}/sessions/{$sessionId}");

            return $response->json() ?? ['status' => true];
        } catch (Exception $e) {
            Log::error("WhatsAppSessionService deleteSession error: {$e->getMessage()}", ['session_id' => $sessionId]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch all WhatsApp groups for a connected session.
     */
    public function getGroups(string $sessionId): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['x-session-secret' => $this->secretKey])
                ->get("{$this->baseUrl}/sessions/{$sessionId}/groups");

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => false,
                'message' => $response->json('message') ?? 'Failed to fetch groups',
            ];
        } catch (Exception $e) {
            Log::error("WhatsAppSessionService getGroups error: {$e->getMessage()}", ['session_id' => $sessionId]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch participants of a specific WhatsApp group.
     */
    public function getGroupParticipants(string $sessionId, string $groupJid): array
    {
        try {
            $encodedJid = urlencode($groupJid);
            $response = Http::timeout(20)
                ->withHeaders(['x-session-secret' => $this->secretKey])
                ->get("{$this->baseUrl}/sessions/{$sessionId}/groups/{$encodedJid}/participants");

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => false,
                'message' => $response->json('message') ?? 'Failed to fetch participants',
            ];
        } catch (Exception $e) {
            Log::error("WhatsAppSessionService getGroupParticipants error: {$e->getMessage()}", [
                'session_id' => $sessionId,
                'group_jid' => $groupJid,
            ]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
