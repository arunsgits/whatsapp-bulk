<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookForwarder
{
    public function forward(Request $request): void
    {
        if (!config('whatsapp.forward_webhook_enabled', false)) {
            return;
        }

        $url = config('whatsapp.forward_webhook_url');
        if (empty($url)) {
            return;
        }

        try {
            if ($request->isMethod('GET')) {
                $this->forwardVerification($request, $url);
                return;
            }

            $this->forwardEvent($request, $url);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp webhook forward failed: ' . $e->getMessage(), [
                'url' => $url,
            ]);
        }
    }

    private function forwardVerification(Request $request, string $url): void
    {
        $query = $request->query();
        $forwardToken = config('whatsapp.forward_webhook_verify_token');

        if ($forwardToken) {
            $query['hub_verify_token'] = $forwardToken;
        }

        $response = Http::timeout(10)->get($url, $query);

        Log::info('WhatsApp webhook verification forwarded', [
            'url' => $url,
            'status' => $response->status(),
        ]);
    }

    private function forwardEvent(Request $request, string $url): void
    {
        $headers = array_filter([
            'Content-Type' => $request->header('Content-Type', 'application/json'),
            'X-Hub-Signature' => $request->header('X-Hub-Signature'),
            'X-Hub-Signature-256' => $request->header('X-Hub-Signature-256'),
        ]);

        $response = Http::timeout(15)
            ->withHeaders($headers)
            ->withBody($request->getContent(), 'application/json')
            ->post($url);

        Log::info('WhatsApp webhook event forwarded', [
            'url' => $url,
            'status' => $response->status(),
        ]);
    }
}
