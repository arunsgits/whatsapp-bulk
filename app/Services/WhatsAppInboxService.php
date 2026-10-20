<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Settings;
use App\Models\User;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappCampaign;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WhatsAppInboxService
{
    private function getSettings(?int $userId = null): ?Settings
    {
        if ($userId) {
            $owned = Settings::where('userId', $userId)
                ->where('isActive', true)
                ->orderByDesc('id')
                ->first();

            if ($owned) {
                return $owned;
            }

            // Prefer this user's row even if isActive flag is stale
            $anyOwned = Settings::where('userId', $userId)->orderByDesc('id')->first();
            if ($anyOwned) {
                return $anyOwned;
            }

            // Never fall back to another company's WhatsApp credentials
            return null;
        }

        // Legacy callers without user context — last resort only
        return Settings::where('isActive', true)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Resolve company/admin user id that owns this WhatsApp inbox conversation.
     */
    public function resolveOwnerUserId(WhatsappConversation $conversation): ?int
    {
        $meta = is_array($conversation->metadata) ? $conversation->metadata : [];

        if (! empty($meta['owner_user_id']) && is_numeric($meta['owner_user_id'])) {
            return (int) $meta['owner_user_id'];
        }

        $legacyId = $meta['legacy_conversation_id'] ?? null;
        if ($legacyId) {
            $legacy = Conversation::find($legacyId);
            if ($legacy && $legacy->user_id) {
                return (int) $legacy->user_id;
            }
        }

        $leadUserId = $conversation->lead?->userId
            ?? ($conversation->lead_id ? Lead::where('id', $conversation->lead_id)->value('userId') : null);
        if ($leadUserId) {
            return (int) $leadUserId;
        }

        if (! empty($conversation->assigned_user_id)) {
            return (int) $conversation->assigned_user_id;
        }

        return null;
    }

    private function settingsForConversation(WhatsappConversation $conversation): ?Settings
    {
        return $this->getSettings($this->resolveOwnerUserId($conversation));
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10 && preg_match('/^[6-9]/', $phone)) {
            return '91' . $phone;
        }
        return $phone;
    }

    public function normalizedPhone(string $phone): string
    {
        return $this->normalizePhone($phone);
    }

    public function sendTextMessage(
        WhatsappConversation $conversation,
        string $text,
        ?int $sentBy = null,
        ?string &$errorMessage = null,
        ?WhatsappMessage $replyTo = null
    ): ?WhatsappMessage {
        $settings = $this->settingsForConversation($conversation);
        if (!$settings || !$settings->access_token || !$settings->phoneNumberId) {
            $errorMessage = 'WhatsApp is not configured for this account. Save Phone Number ID and Access Token in Settings.';
            return null;
        }

        $phone = $this->normalizePhone($conversation->phone_number);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $text],
        ];

        if ($replyTo?->meta_message_id) {
            $payload['context'] = ['message_id' => $replyTo->meta_message_id];
        }

        $replyMeta = $this->buildReplyMetadata($conversation, $replyTo);

        try {
            $response = Http::withToken($settings->access_token)
                ->post("https://graph.facebook.com/v21.0/{$settings->phoneNumberId}/messages", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['messages'][0]['id'])) {
                $message = WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'direction' => 'outgoing',
                    'message' => $text,
                    'message_type' => 'text',
                    'meta_message_id' => $body['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => array_merge(['sent_by' => $sentBy], $replyMeta),
                ]);

                $conversation->update(['last_message_at' => now()]);
                $this->mirrorOutgoingMessageToLegacy($message, $conversation);

                return $message;
            }

            $errorMessage = $body['error']['message'] ?? 'Meta API rejected the message.';
            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send text exception', ['error' => $e->getMessage()]);
            $errorMessage = $e->getMessage();
            return null;
        }
    }

    public function sendTemplateMessage(
        WhatsappConversation $conversation,
        string $templateName,
        array $variables = [],
        ?int $sentBy = null
    ): ?WhatsappMessage {
        $settings = $this->settingsForConversation($conversation);
        if (!$settings || !$settings->access_token || !$settings->phoneNumberId) {
            return null;
        }

        $phone = $this->normalizePhone($conversation->phone_number);

        $components = [];
        if (!empty($variables)) {
            $parameters = [];
            foreach ($variables as $variable) {
                $parameters[] = ['type' => 'text', 'text' => $variable];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'en'],
                'components' => $components
            ]
        ];

        try {
            $response = Http::withToken($settings->access_token)
                ->post("https://graph.facebook.com/v21.0/{$settings->phoneNumberId}/messages", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['messages'][0]['id'])) {
                $message = WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'direction' => 'outgoing',
                    'message' => '[Template Message: ' . $templateName . ']',
                    'message_type' => 'template',
                    'meta_message_id' => $body['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => ['sent_by' => $sentBy, 'template_name' => $templateName, 'variables' => $variables],
                ]);

                $conversation->update(['last_message_at' => now()]);
                return $message;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send template exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function sendMediaMessage(
        WhatsappConversation $conversation,
        UploadedFile $file,
        ?string $caption = null,
        ?int $sentBy = null,
        ?string &$errorMessage = null,
        bool $isVoiceNote = false
    ): ?WhatsappMessage {
        $settings = $this->settingsForConversation($conversation);
        if (!$settings || !$settings->access_token || !$settings->phoneNumberId) {
            $errorMessage = 'WhatsApp settings not configured for this account.';
            return null;
        }

        $phone = $this->normalizePhone($conversation->phone_number);

        // Upload media to Meta
        try {
            $mime = $file->getMimeType();
            $type = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : (str_starts_with($mime, 'audio/') ? 'audio' : 'document'));

            $response = Http::withToken($settings->access_token)
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName(), ['Content-Type' => $mime])
                ->post("https://graph.facebook.com/v21.0/{$settings->phoneNumberId}/media", [
                    'messaging_product' => 'whatsapp',
                ]);

            $body = $response->json();
            if (!$response->successful() || !isset($body['id'])) {
                $errorMessage = $body['error']['message'] ?? 'Failed to upload media to Meta.';
                return null;
            }

            $mediaId = $body['id'];

            // Save media file locally
            $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/whatsapp'), $filename);
            $localUrl = '/uploads/whatsapp/' . $filename;

            // Send message with media id
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => $type,
                $type => [
                    'id' => $mediaId,
                ]
            ];

            if ($caption && in_array($type, ['image', 'video', 'document'])) {
                $payload[$type]['caption'] = $caption;
            }

            $sendResponse = Http::withToken($settings->access_token)
                ->post("https://graph.facebook.com/v21.0/{$settings->phoneNumberId}/messages", $payload);

            $sendBody = $sendResponse->json();
            if ($sendResponse->successful() && isset($sendBody['messages'][0]['id'])) {
                $message = WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'direction' => 'outgoing',
                    'message' => $caption ?? '[' . ucfirst($type) . ']',
                    'message_type' => $type,
                    'media_url' => $localUrl,
                    'media_type' => $mime,
                    'meta_message_id' => $sendBody['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => ['sent_by' => $sentBy, 'meta_media_id' => $mediaId],
                ]);

                $conversation->update(['last_message_at' => now()]);
                return $message;
            }

            $errorMessage = $sendBody['error']['message'] ?? 'Failed to send media message.';
            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send media exception', ['error' => $e->getMessage()]);
            $errorMessage = $e->getMessage();
            return null;
        }
    }

    /**
     * Send an image or media message by direct public URL via Meta WhatsApp Cloud API.
     */
    public function sendMediaUrlMessage(
        WhatsappConversation $conversation,
        string $mediaUrl,
        string $type = 'image',
        ?string $caption = null,
        ?int $sentBy = null,
        ?string &$errorMessage = null
    ): ?WhatsappMessage {
        $settings = $this->settingsForConversation($conversation);
        if (!$settings || !$settings->access_token || !$settings->phoneNumberId) {
            $errorMessage = 'WhatsApp settings not configured for this account.';
            return null;
        }

        $phone = $this->normalizePhone($conversation->phone_number);

        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => $type,
                $type => [
                    'link' => $mediaUrl,
                ]
            ];

            if ($caption && in_array($type, ['image', 'video', 'document'])) {
                $payload[$type]['caption'] = $caption;
            }

            $response = Http::withToken($settings->access_token)
                ->timeout(30)
                ->post("https://graph.facebook.com/v21.0/{$settings->phoneNumberId}/messages", $payload);

            $sendBody = $response->json();
            if ($response->successful() && isset($sendBody['messages'][0]['id'])) {
                $metaId = $sendBody['messages'][0]['id'];
                $message = WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'direction' => 'outgoing',
                    'message' => $caption ?? '[' . ucfirst($type) . ']',
                    'message_type' => $type,
                    'media_url' => $mediaUrl,
                    'media_type' => 'image/jpeg',
                    'meta_message_id' => $metaId,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => ['sent_by' => $sentBy, 'media_url' => $mediaUrl, 'by_link' => true],
                ]);

                $conversation->update(['last_message_at' => now()]);

                // Sync with legacy Conversation/Message table if linked
                $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
                $legacyId = $meta['legacy_conversation_id'] ?? null;
                if ($legacyId) {
                    Message::create([
                        'conversation_id' => $legacyId,
                        'whatsapp_message_id' => $metaId,
                        'direction' => 'outbound',
                        'type' => $type,
                        'content' => $caption ?? '[' . ucfirst($type) . ']',
                        'media_url' => $mediaUrl,
                        'media_type' => 'image/jpeg',
                        'status' => 'sent',
                        'whatsapp_timestamp' => now(),
                        'is_read' => true,
                        'metadata' => ['sent_by' => $sentBy, 'media_url' => $mediaUrl, 'by_link' => true],
                    ]);
                    Conversation::where('id', $legacyId)->update([
                        'last_message_at' => now(),
                        'last_message_preview' => substr($caption ?? '[' . ucfirst($type) . ']', 0, 100),
                    ]);
                }

                return $message;
            }

            $errorMessage = $sendBody['error']['message'] ?? 'Failed to send media URL message.';
            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send media URL exception', ['error' => $e->getMessage()]);
            $errorMessage = $e->getMessage();
            return null;
        }
    }

    public function sendLocationMessage(
        WhatsappConversation $conversation,
        float $latitude,
        float $longitude,
        ?string $name = null,
        ?string $address = null,
        ?int $sentBy = null,
        ?string &$errorMessage = null
    ): ?WhatsappMessage {
        $settings = $this->settingsForConversation($conversation);
        if (!$settings || !$settings->access_token || !$settings->phoneNumberId) {
            $errorMessage = 'WhatsApp settings not configured for this account.';
            return null;
        }

        $phone = $this->normalizePhone($conversation->phone_number);

        $locationPayload = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
        if ($name) $locationPayload['name'] = $name;
        if ($address) $locationPayload['address'] = $address;

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'location',
            'location' => $locationPayload,
        ];

        try {
            $response = Http::withToken($settings->access_token)
                ->post("https://graph.facebook.com/v21.0/{$settings->phoneNumberId}/messages", $payload);

            $body = $response->json();
            if ($response->successful() && isset($body['messages'][0]['id'])) {
                $messageText = $name ?: ($address ?: "Location: {$latitude},{$longitude}");

                $message = WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'direction' => 'outgoing',
                    'message' => $messageText,
                    'message_type' => 'location',
                    'meta_message_id' => $body['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => [
                        'sent_by' => $sentBy,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'name' => $name,
                        'address' => $address,
                        'location' => [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'name' => $name,
                            'address' => $address,
                        ],
                    ],
                ]);

                $conversation->update(['last_message_at' => now()]);
                $this->mirrorOutgoingMessageToLegacy($message, $conversation);

                return $message;
            }

            $errorMessage = $body['error']['message'] ?? 'Failed to send location.';
            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send location exception', ['error' => $e->getMessage()]);
            $errorMessage = $e->getMessage();
            return null;
        }
    }

    public function deleteMessage(WhatsappMessage $message, ?int $sentBy = null): bool
    {
        $conversation = $message->conversation;
        $settings = $conversation
            ? $this->settingsForConversation($conversation)
            : $this->getSettings();
        if (!$settings || !$settings->access_token || !$settings->phoneNumberId || !$message->meta_message_id) {
            return false;
        }

        try {
            $response = Http::withToken($settings->access_token)
                ->delete("https://graph.facebook.com/v21.0/{$message->meta_message_id}");

            if ($response->successful()) {
                $message->update([
                    'message_type' => 'revoked',
                    'message' => 'This message was deleted.',
                ]);
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsApp delete message exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendReaction(
        WhatsappConversation $conversation,
        WhatsappMessage $message,
        ?string $emoji,
        ?int $sentBy = null,
        ?string &$errorMessage = null
    ): ?array {
        $settings = $this->settingsForConversation($conversation);
        if (! $settings || ! $settings->access_token || ! $settings->phoneNumberId) {
            $errorMessage = 'WhatsApp is not configured for this account. Save Phone Number ID and Access Token in Settings.';
            return null;
        }

        $message = $this->ensureMessageMetaId($message);
        if (! $message->meta_message_id) {
            $errorMessage = 'This message cannot be reacted to because it has no WhatsApp message id yet.';
            return null;
        }

        $phone = $this->normalizePhone($conversation->phone_number);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'reaction',
            'reaction' => [
                'message_id' => $message->meta_message_id,
                'emoji' => $emoji ?: '',
            ],
        ];

        try {
            $response = Http::withToken($settings->access_token)
                ->post("https://graph.facebook.com/v21.0/{$settings->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $meta = is_array($message->metadata) ? $message->metadata : [];
                $reactions = collect($meta['reactions'] ?? [])
                    ->filter(fn ($row) => is_array($row));

                $reactions = $reactions->reject(
                    fn ($row) => array_key_exists('sent_by', $row) && (int) $row['sent_by'] === (int) $sentBy
                );

                if ($emoji) {
                    $user = $sentBy ? User::query()->select(['id', 'name'])->find($sentBy) : null;
                    $reactions->push([
                        'emoji' => $emoji,
                        'sent_by' => $sentBy,
                        'sent_by_name' => $user?->name,
                        'source' => 'user',
                        'at' => now()->toIso8601String(),
                        'reacted_at' => now()->toIso8601String(),
                    ]);
                }

                $meta['reactions'] = $reactions->values()->all();
                $message->update(['metadata' => $meta]);
                $this->syncReactionsToLegacyMessage($message);

                return $message->fresh()->reactionDetails();
            }

            $errorMessage = $response->json()['error']['message'] ?? 'Failed to send reaction.';

            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send reaction exception', ['error' => $e->getMessage()]);
            $errorMessage = $e->getMessage();

            return null;
        }
    }

    public function ensureMessageMetaId(WhatsappMessage $message): WhatsappMessage
    {
        if ($message->meta_message_id) {
            return $message;
        }

        $legacyMessageId = is_array($message->metadata)
            ? ($message->metadata['legacy_message_id'] ?? null)
            : null;

        if (! $legacyMessageId) {
            return $message;
        }

        $legacy = Message::query()
            ->select(['id', 'whatsapp_message_id'])
            ->find($legacyMessageId);

        if ($legacy?->whatsapp_message_id) {
            $message->update(['meta_message_id' => $legacy->whatsapp_message_id]);

            return $message->fresh();
        }

        return $message;
    }

    private function normalizeWhatsappStatus(?string $status): string
    {
        $status = strtolower((string) ($status ?: 'delivered'));

        return in_array($status, ['sent', 'delivered', 'read', 'failed'], true) ? $status : 'delivered';
    }

    private function legacyMessageMetadata(Message $legacy): array
    {
        $meta = ['legacy_message_id' => $legacy->id];

        if ($legacy->type !== 'location') {
            return $meta;
        }

        $source = is_array($legacy->metadata) ? $legacy->metadata : [];
        $loc = is_array($source['location'] ?? null) ? $source['location'] : $source;

        foreach (['latitude', 'longitude', 'name', 'address'] as $key) {
            if (array_key_exists($key, $loc) && $loc[$key] !== null && $loc[$key] !== '') {
                $meta[$key] = $loc[$key];
            }
        }

        if (isset($meta['latitude'], $meta['longitude'])) {
            $meta['location'] = [
                'latitude' => $meta['latitude'],
                'longitude' => $meta['longitude'],
                'name' => $meta['name'] ?? null,
                'address' => $meta['address'] ?? null,
            ];
        }

        return $meta;
    }

    public function mirrorOutgoingMessageToLegacy(WhatsappMessage $whatsappMessage, WhatsappConversation $conversation): ?Message
    {
        $legacyConversation = $this->resolveLegacyConversation($conversation);
        if (! $legacyConversation) {
            return null;
        }

        $metadata = is_array($whatsappMessage->metadata) ? $whatsappMessage->metadata : [];
        if ($whatsappMessage->message_type === 'location') {
            $metadata['location'] = [
                'latitude' => $metadata['latitude'] ?? null,
                'longitude' => $metadata['longitude'] ?? null,
                'name' => $metadata['name'] ?? null,
                'address' => $metadata['address'] ?? null,
            ];
        }

        $payload = [
            'conversation_id' => $legacyConversation->id,
            'whatsapp_message_id' => $whatsappMessage->meta_message_id,
            'direction' => 'outbound',
            'type' => $whatsappMessage->message_type,
            'content' => (string) $whatsappMessage->message,
            'metadata' => $metadata,
            'status' => $this->normalizeWhatsappStatus($whatsappMessage->status),
            'whatsapp_timestamp' => $whatsappMessage->sent_at ?? now(),
            'is_read' => true,
        ];

        if ($whatsappMessage->media_url) {
            $payload['media_url'] = $whatsappMessage->media_url;
            $payload['media_type'] = $whatsappMessage->media_type ?: 'image/jpeg';
        }

        $legacy = null;
        if ($whatsappMessage->meta_message_id) {
            $legacy = Message::query()
                ->where('whatsapp_message_id', $whatsappMessage->meta_message_id)
                ->first();
        }

        if ($legacy) {
            $legacy->update($payload);
        } else {
            $legacy = Message::create($payload);
        }

        $waMeta = is_array($whatsappMessage->metadata) ? $whatsappMessage->metadata : [];
        $waMeta['legacy_message_id'] = $legacy->id;
        $whatsappMessage->update(['metadata' => $waMeta]);

        $legacyConversation->update([
            'last_message_at' => now(),
            'last_message_preview' => \Illuminate\Support\Str::limit((string) $whatsappMessage->message, 100),
        ]);

        return $legacy;
    }

    private function resolveLegacyConversation(WhatsappConversation $conversation): ?Conversation
    {
        $legacyId = $this->legacyConversationId($conversation);
        if ($legacyId) {
            return Conversation::find($legacyId);
        }

        $phone = $this->normalizePhone($conversation->phone_number);

        return Conversation::query()
            ->where('phone_number', $conversation->phone_number)
            ->orWhere('phone_number', $phone)
            ->orderByDesc('id')
            ->first();
    }

    private function buildReplyMetadata(WhatsappConversation $conversation, ?WhatsappMessage $replyTo): array
    {
        if (! $replyTo) {
            return [];
        }

        $replyTo = $this->ensureMessageMetaId($replyTo);
        $preview = $replyTo->toReplyPreview($conversation);
        $preview['message_id'] = $replyTo->apiMessageId();
        $preview['whatsapp_message_id'] = $replyTo->id;
        $preview['legacy_message_id'] = $replyTo->legacyMessageId();
        $preview['meta_message_id'] = $replyTo->meta_message_id;

        return array_filter([
            'reply_to_meta_message_id' => $replyTo->meta_message_id,
            'reply_to_message_id' => $replyTo->apiMessageId(),
            'reply_to_whatsapp_message_id' => $replyTo->id,
            'reply_preview' => $preview,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function appendReplyMetadataFromWebhook(array $metadata, ?array $webhookMessage, ?int $conversationId = null): array
    {
        if (! is_array($webhookMessage)) {
            return $metadata;
        }

        $quotedWamid = WhatsappMessage::quotedWamidFromMetadata($webhookMessage);
        if (! $quotedWamid) {
            return $metadata;
        }

        $metadata['reply_to_meta_message_id'] = $quotedWamid;
        $preview = WhatsappMessage::replyPreviewForQuotedWamid($quotedWamid, $conversationId);
        $metadata['reply_preview'] = $preview;
        $metadata['reply_to_message_id'] = $preview['message_id'] ?? null;
        if (isset($preview['whatsapp_message_id'])) {
            $metadata['reply_to_whatsapp_message_id'] = $preview['whatsapp_message_id'];
        }

        return $metadata;
    }

    private function metadataFromWebhookType(string $type, ?array $webhookMessage, ?int $conversationId = null): array
    {
        if (! is_array($webhookMessage)) {
            return [];
        }

        $metadata = [];

        if ($type === 'location') {
            $loc = $webhookMessage['location'] ?? [];

            $metadata = array_filter([
                'latitude' => $loc['latitude'] ?? null,
                'longitude' => $loc['longitude'] ?? null,
                'name' => $loc['name'] ?? null,
                'address' => $loc['address'] ?? null,
                'location' => [
                    'latitude' => $loc['latitude'] ?? null,
                    'longitude' => $loc['longitude'] ?? null,
                    'name' => $loc['name'] ?? null,
                    'address' => $loc['address'] ?? null,
                ],
            ], fn ($value) => $value !== null && $value !== '');
        }

        if ($type === 'order') {
            $rawOrder = $webhookMessage['order'] ?? [];
            $metadata['order'] = app(\App\Services\WhatsAppCatalogService::class)->enrichOrderData(is_array($rawOrder) ? $rawOrder : []);
            $metadata['is_order'] = true;
        }

        if ($type === 'interactive') {
            $metadata['interactive'] = $webhookMessage['interactive'] ?? [];
            $metadata['is_interactive'] = true;
        }

        if (!empty($webhookMessage['context']['referred_product'])) {
            $metadata['referred_product'] = app(\App\Services\WhatsAppCatalogService::class)->enrichReferredProduct($webhookMessage['context']['referred_product']);
            $metadata['is_product_inquiry'] = true;
        }

        return $this->appendReplyMetadataFromWebhook($metadata, $webhookMessage, $conversationId);
    }

    public function resolveIncomingMedia(WhatsappMessage $message)
    {
        return $message->media_url;
    }

    /**
     * Resolve inbox conversation by whatsapp_conversations.id or legacy conversations.id.
     */
    public function resolveConversation($conversationId): ?WhatsappConversation
    {
        if ($conversationId === null || $conversationId === '' || $conversationId === '?') {
            return null;
        }

        if (! is_numeric($conversationId)) {
            return null;
        }

        $conversationId = (int) $conversationId;

        $conversation = WhatsappConversation::find($conversationId);
        if ($conversation) {
            return $conversation;
        }

        $legacy = Conversation::find($conversationId);
        if (! $legacy) {
            return null;
        }

        return $this->ensureWhatsappConversationForLegacy($legacy);
    }

    public function ensureWhatsappConversationForLegacy(Conversation $legacy): WhatsappConversation
    {
        $phone = $this->normalizePhone($legacy->phone_number);
        $digits = preg_replace('/[^0-9]/', '', (string) $legacy->phone_number) ?: '';
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : null;
        $ownerId = $legacy->user_id ? (int) $legacy->user_id : null;

        $candidates = WhatsappConversation::query()
            ->where(function ($query) use ($phone, $legacy, $last10) {
                $query->where('phone_number', $phone)
                    ->orWhere('phone_number', $legacy->phone_number)
                    ->orWhere('metadata->legacy_conversation_id', $legacy->id);
                if ($last10) {
                    $query->orWhere('phone_number', 'like', '%'.$last10);
                }
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $conversation = $candidates->first(function (WhatsappConversation $row) use ($legacy, $ownerId) {
            $meta = is_array($row->metadata) ? $row->metadata : [];
            if ((int) ($meta['legacy_conversation_id'] ?? 0) === (int) $legacy->id) {
                return true;
            }
            if ($ownerId && (int) ($meta['owner_user_id'] ?? 0) === $ownerId) {
                return true;
            }
            if ($ownerId && (int) ($row->assigned_user_id ?? 0) === $ownerId) {
                return true;
            }

            return false;
        });

        // Never reuse another company's chat for the same phone
        if (! $conversation && ! $ownerId) {
            $conversation = $candidates->first();
        }

        if ($conversation) {
            $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
            $meta['legacy_conversation_id'] = $legacy->id;
            if ($ownerId) {
                $meta['owner_user_id'] = $ownerId;
            }
            $conversation->update([
                'metadata' => $meta,
                'assigned_user_id' => $conversation->assigned_user_id ?: $ownerId,
                'contact_name' => $conversation->contact_name ?: $legacy->contact_name,
                'last_message_at' => $conversation->last_message_at ?? $legacy->last_message_at,
            ]);

            return $conversation->fresh();
        }

        return WhatsappConversation::create([
            'phone_number' => $phone,
            'contact_name' => $legacy->contact_name,
            'status' => 'open',
            'assigned_user_id' => $ownerId,
            'last_message_at' => $legacy->last_message_at,
            'metadata' => array_filter([
                'legacy_conversation_id' => $legacy->id,
                'owner_user_id' => $ownerId,
            ]),
        ]);
    }

    /**
     * Resolve inbox message by whatsapp_messages.id or legacy messages.id.
     */
    public function resolveMessage(WhatsappConversation $conversation, $messageId): ?WhatsappMessage
    {
        if ($messageId === null || $messageId === '' || ! is_numeric($messageId)) {
            return null;
        }

        $messageId = (int) $messageId;

        try {
            $legacy = Message::find($messageId);
            if ($legacy) {
                $legacyConversation = $legacy->conversation;
                if ($legacyConversation) {
                    $bridgedConversation = $this->ensureWhatsappConversationForLegacy($legacyConversation);
                    if ($bridgedConversation->id === $conversation->id) {
                        return $this->syncLegacyMessageToWhatsapp($legacy, $bridgedConversation);
                    }
                }
            }

            $message = WhatsappMessage::find($messageId);
            if ($message && $message->conversation_id === $conversation->id) {
                return $message;
            }

            $linked = WhatsappMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('metadata->legacy_message_id', $messageId)
                ->first();

            return $linked ?: null;
        } catch (\Throwable $e) {
            Log::error('Resolve inbox message failed', [
                'conversation_id' => $conversation->id,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function syncLegacyMessageToWhatsapp(Message $legacy, WhatsappConversation $conversation): WhatsappMessage
    {
        $payload = [
            'conversation_id' => $conversation->id,
            'direction' => $legacy->direction === 'inbound' ? 'incoming' : 'outgoing',
            'message' => (string) ($legacy->content ?? ''),
            'message_type' => (string) ($legacy->type ?? 'text'),
            'media_url' => $legacy->media_url,
            'media_type' => $legacy->media_type,
            'status' => $this->normalizeWhatsappStatus($legacy->status),
            'sent_at' => $legacy->whatsapp_timestamp,
            'metadata' => $this->legacyMessageMetadata($legacy),
        ];

        $existing = WhatsappMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('metadata->legacy_message_id', $legacy->id)
            ->first();

        if ($existing) {
            $existing->update(array_filter([
                'meta_message_id' => $legacy->whatsapp_message_id ?: $existing->meta_message_id,
                'message' => $payload['message'],
                'message_type' => $payload['message_type'],
                'media_url' => $payload['media_url'],
                'media_type' => $payload['media_type'],
                'status' => $payload['status'],
                'sent_at' => $payload['sent_at'],
            ], fn ($value) => $value !== null));

            return $existing->fresh();
        }

        if ($legacy->whatsapp_message_id) {
            return WhatsappMessage::updateOrCreate(
                ['meta_message_id' => $legacy->whatsapp_message_id],
                array_merge($payload, ['meta_message_id' => $legacy->whatsapp_message_id])
            );
        }

        return WhatsappMessage::create($payload);
    }

    /**
     * Mirror webhook inbound traffic into whatsapp_conversations / whatsapp_messages.
     */
    public function recordIncomingWebhookMessage(
        string $phoneNumber,
        string $metaMessageId,
        string $type,
        string $content,
        ?string $mediaUrl,
        ?string $mediaType,
        int $timestamp,
        ?array $webhookMessage = null,
        ?int $ownerUserId = null
    ): void {
        $phone = $this->normalizePhone($phoneNumber);

        $candidates = WhatsappConversation::query()
            ->where('phone_number', $phone)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $conversation = null;
        if ($ownerUserId) {
            $conversation = $candidates->first(function (WhatsappConversation $row) use ($ownerUserId) {
                $meta = is_array($row->metadata) ? $row->metadata : [];
                if ((int) ($meta['owner_user_id'] ?? 0) === (int) $ownerUserId) {
                    return true;
                }
                $legacyId = $meta['legacy_conversation_id'] ?? null;
                if ($legacyId) {
                    $legacy = Conversation::find($legacyId);

                    return $legacy && (int) $legacy->user_id === (int) $ownerUserId;
                }

                return (int) ($row->assigned_user_id ?? 0) === (int) $ownerUserId;
            });
        } else {
            $conversation = $candidates->first();
        }

        if (! $conversation) {
            $conversation = WhatsappConversation::create([
                'phone_number' => $phone,
                'contact_name' => null,
                'status' => 'open',
                'assigned_user_id' => $ownerUserId,
                'metadata' => array_filter([
                    'owner_user_id' => $ownerUserId,
                ]),
            ]);
        }

        $legacyQuery = Conversation::query()
            ->where(function ($q) use ($phoneNumber, $phone) {
                $q->where('phone_number', $phoneNumber)->orWhere('phone_number', $phone);
            });
        if ($ownerUserId) {
            $legacyQuery->where('user_id', $ownerUserId);
        }
        $legacy = $legacyQuery->orderByDesc('id')->first();

        $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
        if ($ownerUserId) {
            $meta['owner_user_id'] = $ownerUserId;
        }
        if ($legacy) {
            $meta['legacy_conversation_id'] = $legacy->id;
        }
        $conversation->update([
            'metadata' => $meta,
            'assigned_user_id' => $conversation->assigned_user_id ?: $ownerUserId,
            'contact_name' => $conversation->contact_name ?: ($legacy->contact_name ?? null),
        ]);

        WhatsappMessage::updateOrCreate(
            ['meta_message_id' => $metaMessageId],
            [
                'conversation_id' => $conversation->id,
                'direction' => 'incoming',
                'message' => $content,
                'message_type' => $type,
                'media_url' => $mediaUrl,
                'media_type' => $mediaType,
                'status' => 'delivered',
                'sent_at' => date('Y-m-d H:i:s', $timestamp),
                'metadata' => $this->metadataFromWebhookType($type, $webhookMessage, $conversation->id),
            ]
        );

        $conversation->incrementUnread();
        $conversation->update(['last_message_at' => now()]);
    }

    public function legacyConversationId(WhatsappConversation $conversation): ?int
    {
        if (! is_array($conversation->metadata)) {
            return null;
        }

        $id = $conversation->metadata['legacy_conversation_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function apiConversationId(WhatsappConversation $conversation): int
    {
        return $this->legacyConversationId($conversation) ?? $conversation->id;
    }

    public function isMessagingWindowActive(WhatsappConversation $conversation): bool
    {
        $lastInbound = $conversation->messages()
            ->where('direction', 'incoming')
            ->latest('created_at')
            ->first();

        return $lastInbound
            && $lastInbound->created_at
            && $lastInbound->created_at->greaterThan(now()->subHours(24));
    }

    public function buildConversationCapabilities(WhatsappConversation $conversation): array
    {
        $apiConversationId = $this->apiConversationId($conversation);
        $messagingActive = $this->isMessagingWindowActive($conversation);

        return [
            'id' => $conversation->id,
            'legacy_conversation_id' => $this->legacyConversationId($conversation),
            'inbox_conversation_id' => $apiConversationId,
            'messaging_window_active' => $messagingActive,
            'can_send_freeform' => $messagingActive,
            'can_send_media' => $messagingActive,
            'can_send_template' => true,
            'can_reply' => $messagingActive,
            'can_react' => true,
        ];
    }

    public function buildWhatsappMessageCapabilities(
        WhatsappMessage $message,
        WhatsappConversation $conversation,
        ?bool $messagingWindowActive = null
    ): array {
        $messagingActive = $messagingWindowActive ?? $this->isMessagingWindowActive($conversation);
        $apiConversationId = $this->apiConversationId($conversation);
        $apiMessageId = $message->apiMessageId();
        $canReact = $message->canReact();
        $canReply = $message->canReplyTo($messagingActive);

        return [
            'can_react' => $canReact,
            'can_reply' => $canReply,
            'react_message_id' => $canReact ? $apiMessageId : null,
            'reply_to_message_id' => $canReply ? $apiMessageId : null,
            'inbox_conversation_id' => $apiConversationId,
            'meta_message_id' => $message->meta_message_id,
            'react_endpoint' => $canReact
                ? '/api/whatsapp/inbox/' . $apiConversationId . '/messages/' . $apiMessageId . '/react'
                : null,
            'reply_endpoint' => $canReply
                ? '/api/whatsapp/inbox/' . $apiConversationId . '/send'
                : null,
        ];
    }

    public function buildLegacyMessageCapabilities(
        Message $legacy,
        int $legacyConversationId,
        bool $messagingWindowActive
    ): array {
        $canReact = filled($legacy->whatsapp_message_id);
        $canReply = $messagingWindowActive;

        return [
            'can_react' => $canReact,
            'can_reply' => $canReply,
            'react_message_id' => $canReact ? $legacy->id : null,
            'reply_to_message_id' => $canReply ? $legacy->id : null,
            'inbox_conversation_id' => $legacyConversationId,
            'meta_message_id' => $legacy->whatsapp_message_id,
            'react_endpoint' => $canReact
                ? '/api/whatsapp/inbox/' . $legacyConversationId . '/messages/' . $legacy->id . '/react'
                : null,
            'reply_endpoint' => $canReply
                ? '/api/whatsapp/inbox/' . $legacyConversationId . '/send'
                : null,
        ];
    }

    public function applyIncomingReaction(
        string $phoneNumber,
        string $targetWamid,
        ?string $emoji,
        ?string $reactorMessageId = null
    ): bool {
        $message = WhatsappMessage::findByMetaMessageId($targetWamid);
        if (! $message) {
            return false;
        }

        $conversation = $message->conversation;
        $phone = $this->normalizePhone($phoneNumber);
        $meta = is_array($message->metadata) ? $message->metadata : [];
        $reactions = collect($meta['reactions'] ?? [])->filter(fn ($row) => is_array($row));

        $reactions = $reactions->reject(function ($row) use ($phone) {
            return ($row['source'] ?? null) === 'contact'
                && $this->normalizePhone((string) ($row['phone_number'] ?? '')) === $phone;
        });

        if ($emoji) {
            $reactions->push([
                'emoji' => $emoji,
                'source' => 'contact',
                'phone_number' => $phone,
                'sent_by_name' => $conversation?->contact_name ?? $phoneNumber,
                'contact_name' => $conversation?->contact_name,
                'whatsapp_message_id' => $reactorMessageId,
                'reacted_at' => now()->toIso8601String(),
            ]);
        }

        $meta['reactions'] = $reactions->values()->all();
        $message->update(['metadata' => $meta]);
        $this->syncReactionsToLegacyMessage($message);

        return true;
    }

    private function syncReactionsToLegacyMessage(WhatsappMessage $message): void
    {
        if (! is_array($message->metadata)) {
            return;
        }

        $legacyMessageId = $message->metadata['legacy_message_id'] ?? null;
        if (! is_numeric($legacyMessageId)) {
            $legacy = Message::query()
                ->where('whatsapp_message_id', $message->meta_message_id)
                ->first();
        } else {
            $legacy = Message::find($legacyMessageId);
        }

        if (! $legacy) {
            return;
        }

        $legacyMeta = is_array($legacy->metadata) ? $legacy->metadata : [];
        $legacyMeta['reactions'] = $message->metadata['reactions'] ?? [];
        $legacy->update(['metadata' => $legacyMeta]);
    }

    public function buildLegacyConversationCapabilities(
        Conversation $legacy,
        bool $messagingWindowActive
    ): array {
        return [
            'id' => $legacy->id,
            'legacy_conversation_id' => $legacy->id,
            'inbox_conversation_id' => $legacy->id,
            'messaging_window_active' => $messagingWindowActive,
            'can_send_freeform' => $messagingWindowActive,
            'can_send_media' => $messagingWindowActive,
            'can_send_template' => true,
            'can_reply' => $messagingWindowActive,
            'can_react' => true,
        ];
    }

    public function clearPendingAiDeferral(WhatsappConversation $conversation)
    {
        // AI replies not implemented in this version
    }

    public function sendCampaign(WhatsappCampaign $campaign)
    {
        $campaign->markAsSending();
        // Simplified campaign sending loop
        $leads = Lead::whereNotNull('whatsapp')->get();
        $campaign->update(['total_recipients' => $leads->count()]);

        $sent = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            $conversation = WhatsappConversation::firstOrCreate(
                ['phone_number' => $this->normalizePhone($lead->whatsapp)],
                ['contact_name' => $lead->name, 'lead_id' => $lead->id]
            );

            $msg = null;
            if ($campaign->template_id) {
                $msg = $this->sendTemplateMessage($conversation, $campaign->template->name, [], $campaign->created_by);
            } elseif ($campaign->message) {
                $msg = $this->sendTextMessage($conversation, $campaign->message, $campaign->created_by);
            }

            if ($msg) {
                $sent++;
                $campaign->incrementSent();
            } else {
                $failed++;
                $campaign->incrementFailed();
            }
        }

        $campaign->markAsCompleted();
    }
}
