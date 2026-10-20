<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappMessage extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'lead_id',
        'direction',
        'message',
        'message_type',
        'media_url',
        'media_type',
        'meta_message_id',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopeUnread($query)
    {
        return $query->incoming()->where('status', '!=', 'read');
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead()
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markAsFailed()
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    public function isRevoked(): bool
    {
        return $this->message_type === 'revoked';
    }

    public function legacyMessageId(): ?int
    {
        if (! is_array($this->metadata)) {
            return null;
        }

        $id = $this->metadata['legacy_message_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function canReact(): bool
    {
        return filled($this->meta_message_id)
            && ! $this->isRevoked()
            && ! in_array($this->message_type, ['reaction', 'revoked'], true);
    }

    public function canReplyTo(bool $messagingWindowActive = true): bool
    {
        return $messagingWindowActive
            && ! $this->isRevoked()
            && ! in_array($this->message_type, ['reaction', 'revoked'], true);
    }

    public function apiMessageId(): int
    {
        return $this->legacyMessageId() ?? $this->id;
    }

    public function messageHtml(): string
    {
        return static::linkifyHtml((string) ($this->message ?? ''));
    }

    public static function linkifyHtml(?string $text): string
    {
        $text = (string) ($text ?? '');
        if ($text === '') {
            return '';
        }

        $pattern = '/(?:https?:\/\/|www\.)[^\s<>"\']+/iu';
        $out = '';
        $offset = 0;

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $url = $match[0];
                $pos = (int) $match[1];
                $out .= e(substr($text, $offset, $pos - $offset));

                $trimmed = rtrim($url, '.,;:!?)]}');
                $trailing = substr($url, strlen($trimmed));
                $href = preg_match('#^https?://#i', $trimmed) ? $trimmed : 'https://'.$trimmed;
                $out .= '<a class="wa-msg-link" href="'.e($href).'" target="_blank" rel="noopener noreferrer">'.e($trimmed).'</a>'.e($trailing);
                $offset = $pos + strlen($url);
            }
        }

        $out .= e(substr($text, $offset));

        return nl2br($out);
    }

    public function replyPreviewText(): string
    {
        if ($this->isRevoked()) {
            return 'This message was deleted';
        }

        return match ($this->message_type) {
            'image' => 'Photo',
            'video' => 'Video',
            'audio' => 'Audio',
            'document' => \Illuminate\Support\Str::limit($this->message ?: 'Document', 80),
            'location' => 'Location',
            'contacts' => 'Contact',
            default => \Illuminate\Support\Str::limit((string) ($this->message ?? ''), 120) ?: 'Message',
        };
    }

    public function toReplyPreview(WhatsappConversation $conversation): array
    {
        return [
            'message_id' => $this->id,
            'author' => $this->direction === 'outgoing'
                ? 'You'
                : ($conversation->contact_name ?? $conversation->phone_number ?? 'Contact'),
            'text' => $this->replyPreviewText(),
            'message_type' => (string) $this->message_type,
            'direction' => (string) $this->direction,
        ];
    }

    protected static array $wamidKeyIndexCache = [];

    public static function extractWamidMessageKey(?string $wamid): ?string
    {
        if (! is_string($wamid) || $wamid === '') {
            return null;
        }

        $b64 = preg_replace('/^wamid\./i', '', $wamid);
        $raw = base64_decode(strtr((string) $b64, '-_', '+/'), true);
        if ($raw === false || $raw === '') {
            return null;
        }

        if (! preg_match_all('/[0-9A-Fa-f]{16,64}/', $raw, $matches) || empty($matches[0])) {
            return null;
        }

        return strtoupper((string) end($matches[0]));
    }

    public static function findByMetaMessageId(string $wamid, ?int $conversationId = null): ?self
    {
        $exact = static::withTrashed()->where('meta_message_id', $wamid)->first();
        if ($exact) {
            return $exact;
        }

        $key = static::extractWamidMessageKey($wamid);
        if (! $key || ! $conversationId) {
            return null;
        }

        if (! isset(static::$wamidKeyIndexCache[$conversationId])) {
            static::$wamidKeyIndexCache[$conversationId] = [];
            $candidates = static::withTrashed()
                ->where('conversation_id', $conversationId)
                ->whereNotNull('meta_message_id')
                ->orderByDesc('id')
                ->limit(800)
                ->get();

            foreach ($candidates as $candidate) {
                $candidateKey = static::extractWamidMessageKey($candidate->meta_message_id);
                if ($candidateKey && ! isset(static::$wamidKeyIndexCache[$conversationId][$candidateKey])) {
                    static::$wamidKeyIndexCache[$conversationId][$candidateKey] = $candidate;
                }
            }
        }

        return static::$wamidKeyIndexCache[$conversationId][$key] ?? null;
    }

    public static function quotedWamidFromMetadata(array $meta): ?string
    {
        if (filled($meta['reply_to_meta_message_id'] ?? null)) {
            return (string) $meta['reply_to_meta_message_id'];
        }

        $context = $meta['context'] ?? null;
        if (is_array($context)) {
            $id = $context['id'] ?? $context['message_id'] ?? null;
            if (filled($id)) {
                return (string) $id;
            }
        }

        return null;
    }

    public static function replyPreviewForQuotedWamid(
        string $quotedWamid,
        ?int $conversationId = null,
        ?int $fallbackMessageId = null
    ): array {
        $quoted = static::findByMetaMessageId($quotedWamid, $conversationId);
        if ($quoted) {
            $conversation = $quoted->conversation ?? new WhatsappConversation();
            $preview = $quoted->toReplyPreview($conversation);
            $preview['meta_message_id'] = $quoted->meta_message_id ?: $quotedWamid;
            $preview['message_id'] = $quoted->apiMessageId();
            $preview['whatsapp_message_id'] = $quoted->id;
            $preview['legacy_message_id'] = $quoted->legacyMessageId();

            return $preview;
        }

        $legacyQuoted = Message::query()
            ->where('whatsapp_message_id', $quotedWamid)
            ->first();

        if ($legacyQuoted) {
            return [
                'message_id' => $legacyQuoted->id,
                'legacy_message_id' => $legacyQuoted->id,
                'author' => $legacyQuoted->direction === 'inbound' ? 'Contact' : 'You',
                'text' => (string) ($legacyQuoted->content ?? 'Message'),
                'message_type' => (string) ($legacyQuoted->type ?? 'text'),
                'direction' => $legacyQuoted->direction === 'inbound' ? 'incoming' : 'outgoing',
                'meta_message_id' => $quotedWamid,
            ];
        }

        return [
            'message_id' => $fallbackMessageId,
            'meta_message_id' => $quotedWamid,
            'author' => 'Message',
            'text' => 'Original message',
            'message_type' => 'text',
            'direction' => 'incoming',
        ];
    }

    public static function replyContextFromMetadata(array $meta, ?int $conversationId = null): ?array
    {
        if (is_array($meta['reply_preview'] ?? null)) {
            return $meta['reply_preview'];
        }

        $replyToWhatsappId = $meta['reply_to_whatsapp_message_id'] ?? null;
        if (is_numeric($replyToWhatsappId)) {
            $quoted = static::find((int) $replyToWhatsappId);
            if ($quoted && ($conversationId === null || $quoted->conversation_id === $conversationId)) {
                $conversation = $quoted->conversation ?? new WhatsappConversation();
                $preview = $quoted->toReplyPreview($conversation);
                $preview['message_id'] = $quoted->apiMessageId();
                $preview['whatsapp_message_id'] = $quoted->id;
                $preview['legacy_message_id'] = $quoted->legacyMessageId();
                $preview['meta_message_id'] = $quoted->meta_message_id;

                return $preview;
            }
        }

        $quotedWamid = static::quotedWamidFromMetadata($meta);
        if (! $quotedWamid) {
            return null;
        }

        $fallbackMessageId = is_numeric($meta['reply_to_message_id'] ?? null)
            ? (int) $meta['reply_to_message_id']
            : null;

        return static::replyPreviewForQuotedWamid($quotedWamid, $conversationId, $fallbackMessageId);
    }

    public function replyContext(): ?array
    {
        $meta = is_array($this->metadata) ? $this->metadata : [];
        $context = static::replyContextFromMetadata($meta, $this->conversation_id);
        if ($context) {
            return $context;
        }

        if (filled($this->meta_message_id)) {
            $legacy = Message::query()
                ->where('whatsapp_message_id', $this->meta_message_id)
                ->first();

            if ($legacy) {
                return static::replyContextFromLegacy($legacy, skipWhatsapp: true);
            }
        }

        return null;
    }

    public static function replyContextFromLegacy(Message $legacy, bool $skipWhatsapp = false): ?array
    {
        if (! $skipWhatsapp && filled($legacy->whatsapp_message_id)) {
            $whatsappMessage = static::query()
                ->where('meta_message_id', $legacy->whatsapp_message_id)
                ->first();

            if ($whatsappMessage) {
                $waMeta = is_array($whatsappMessage->metadata) ? $whatsappMessage->metadata : [];
                $context = static::replyContextFromMetadata($waMeta, $whatsappMessage->conversation_id);
                if ($context) {
                    return $context;
                }
            }
        }

        $meta = is_array($legacy->metadata) ? $legacy->metadata : [];

        return static::replyContextFromMetadata($meta, $legacy->conversation_id);
    }

    public function displayMediaUrl(): ?string
    {
        if (! $this->media_url) {
            return null;
        }

        $path = parse_url($this->media_url, PHP_URL_PATH) ?: $this->media_url;

        if (preg_match('#(?:^|/)whatsapp/inbox/media/([a-f0-9-]+\.[a-z0-9]+)$#i', $path, $matches)) {
            return route('whatsapp.media', ['filename' => $matches[1]], false);
        }

        if (preg_match('#(?:^|/)uploads/whatsapp/([a-f0-9-]+\.[a-z0-9]+)$#i', $path, $matches)) {
            return route('whatsapp.media', ['filename' => $matches[1]], false);
        }

        if (preg_match('/^\d{5,}$/', (string) $this->media_url)) {
            return route('whatsapp.meta_media', ['message' => $this->id], false);
        }

        return $this->media_url;
    }

    public function reactionEmojis(): array
    {
        return collect($this->reactionDetails())
            ->pluck('emoji')
            ->unique()
            ->values()
            ->all();
    }

    public function reactionDetails(): array
    {
        return collect($this->metadata['reactions'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['emoji'] ?? null))
            ->map(fn ($row) => $this->formatReactionRow($row))
            ->values()
            ->all();
    }

    public function reactionSummary(): array
    {
        return collect($this->reactionDetails())
            ->groupBy('emoji')
            ->map(function ($group, $emoji) {
                return [
                    'emoji' => (string) $emoji,
                    'count' => $group->count(),
                    'reactors' => $group->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public static function reactionDetailsFromMetadata(?array $metadata): array
    {
        return (new static(['metadata' => $metadata ?? []]))->reactionDetails();
    }

    public static function reactionSummaryFromMetadata(?array $metadata): array
    {
        return (new static(['metadata' => $metadata ?? []]))->reactionSummary();
    }

    private function formatReactionRow(array $row): array
    {
        $sentBy = $row['sent_by'] ?? null;
        $source = (string) ($row['source'] ?? ($sentBy ? 'user' : 'contact'));
        $user = is_numeric($sentBy) ? User::query()->select(['id', 'name', 'email'])->find($sentBy) : null;

        $name = $row['sent_by_name']
            ?? $user?->name
            ?? ($source === 'contact' ? ($row['contact_name'] ?? 'Contact') : 'Unknown');

        return [
            'emoji' => (string) ($row['emoji'] ?? ''),
            'sent_by' => is_numeric($sentBy) ? (int) $sentBy : null,
            'sent_by_name' => $name,
            'sent_by_email' => $user?->email,
            'source' => $source,
            'phone_number' => $row['phone_number'] ?? null,
            'reacted_at' => $row['reacted_at'] ?? $row['at'] ?? null,
            'whatsapp_message_id' => $row['whatsapp_message_id'] ?? null,
        ];
    }

    public static function locationDataFromLegacy(Message $legacy): ?array
    {
        if ($legacy->type !== 'location') {
            return null;
        }

        if (filled($legacy->whatsapp_message_id)) {
            $whatsappMessage = static::query()
                ->where('meta_message_id', $legacy->whatsapp_message_id)
                ->first();

            if ($whatsappMessage) {
                return $whatsappMessage->locationData();
            }
        }

        $source = is_array($legacy->metadata) ? $legacy->metadata : [];
        $loc = is_array($source['location'] ?? null) ? $source['location'] : $source;

        $payload = new static([
            'message' => (string) ($legacy->content ?? ''),
            'message_type' => 'location',
            'metadata' => [
                'latitude' => $loc['latitude'] ?? null,
                'longitude' => $loc['longitude'] ?? null,
                'name' => $loc['name'] ?? null,
                'address' => $loc['address'] ?? null,
            ],
        ]);

        return $payload->locationData();
    }

    public function locationData(): ?array
    {
        $meta = $this->metadata ?? [];
        $lat = $meta['latitude'] ?? null;
        $lng = $meta['longitude'] ?? null;

        if (($lat === null || $lng === null) && is_string($this->message) && preg_match('/Location:\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/i', $this->message, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
        }

        if ($lat === null || $lng === null) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $name = filled($meta['name'] ?? null) ? (string) $meta['name'] : null;
        $address = filled($meta['address'] ?? null) ? (string) $meta['address'] : null;

        $previewUrl = sprintf(
            'https://static-maps.yandex.ru/1.x/?lang=en_US&ll=%1$s,%2$s&z=15&l=map&size=450,200&pt=%1$s,%2$s,pm2rdm',
            $lng,
            $lat
        );

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'name' => $name,
            'address' => $address,
            'maps_url' => 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng),
            'preview_url' => $previewUrl,
        ];
    }

    public function contactsData(): array
    {
        $contacts = collect($this->metadata['contacts'] ?? [])
            ->filter(fn ($c) => is_array($c))
            ->values();

        if ($contacts->isEmpty() && is_string($this->message) && preg_match('/^Shared contact:\s*(.+)$/i', $this->message, $m)) {
            $contacts = collect([[
                'name' => trim($m[1]),
                'phones' => [],
            ]]);
        }

        return $contacts->map(function ($c) {
            $name = trim((string) ($c['name'] ?? 'Contact')) ?: 'Contact';
            $phones = collect($c['phones'] ?? [])
                ->filter(fn ($p) => is_array($p))
                ->map(function ($p) {
                    $phone = trim((string) ($p['phone'] ?? ''));
                    $waId = preg_replace('/\D+/', '', (string) ($p['wa_id'] ?? ''));
                    $display = $phone !== '' ? $phone : ($waId !== '' ? '+' . $waId : '');
                    $digits = preg_replace('/\D+/', '', $phone !== '' ? $phone : $waId);

                    return [
                        'phone' => $phone !== '' ? $phone : null,
                        'wa_id' => $waId !== '' ? $waId : null,
                        'type' => $p['type'] ?? null,
                        'display' => $display,
                        'tel_url' => $digits !== '' ? 'tel:+' . ltrim($digits, '+') : null,
                        'wa_url' => $waId !== '' ? 'https://wa.me/' . $waId : null,
                    ];
                })
                ->filter(fn ($p) => $p['display'] !== '')
                ->values()
                ->all();

            return [
                'name' => $name,
                'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
                'phones' => $phones,
            ];
        })->all();
    }
}
