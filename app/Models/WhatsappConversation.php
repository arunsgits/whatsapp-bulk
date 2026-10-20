<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappConversation extends Model
{
    use SoftDeletes, Blameable;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_ARCHIVED = 'archived';
    public const FILTER_ALL = 'all';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    public const TAG_PENDING_PAYMENT = 'pending_payment';
    public const TAG_PAID = 'paid';
    public const TAG_IMPORTANT = 'important';

    public const TAGS = [
        self::TAG_PENDING_PAYMENT,
        self::TAG_PAID,
        self::TAG_IMPORTANT,
    ];

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'lead_id',
        'phone_number',
        'whatsapp_phone_id',
        'contact_name',
        'profile_picture',
        'status',
        'assigned_user_id',
        'unread_count',
        'last_message_at',
        'last_read_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];

    protected $appends = [
        'active_tags',
        'tags',
        'status_label',
        'is_important',
        'important',
        'is_closed',
        'is_archived',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(WhatsappMessage::class, 'conversation_id')
            ->where('message_type', '!=', 'reaction')
            ->latestOfMany();
    }

    public function followups()
    {
        return $this->hasMany(WhatsappFollowup::class, 'conversation_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeUnread($query)
    {
        return $query->where('unread_count', '>', 0);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeHasTag($query, string $tag)
    {
        return $query->where('metadata->tags->'.$tag, true);
    }

    public function scopeInboxFilter($query, ?string $filter)
    {
        if ($filter === 'unread') {
            return $query->where('unread_count', '>', 0);
        }

        if ($filter === '' || $filter === 'all') {
            return $query;
        }

        if ($filter === 'active' || $filter === 'open') {
            return $query->where('status', self::STATUS_OPEN);
        }

        if (in_array($filter, self::STATUSES, true)) {
            return $query->where('status', $filter);
        }

        if ($filter !== null && $filter !== '') {
            return $query->hasTag($filter);
        }

        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope chats to a company owner (webhook chats often have no lead).
     * Uses a subquery for leads so SoftDeletes on Lead cannot break inbox listing
     * when the leads.deleted_at column is missing.
     */
    public function scopeOwnedBy($query, int $ownerId)
    {
        return $query->where(function ($q) use ($ownerId) {
            $q->where('metadata->owner_user_id', $ownerId)
                ->orWhere('assigned_user_id', $ownerId)
                ->orWhereIn('lead_id', function ($sub) use ($ownerId) {
                    $sub->select('id')
                        ->from('leads')
                        ->where('userId', $ownerId);
                })
                ->orWhereRaw(
                    "CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.legacy_conversation_id')) AS UNSIGNED) IN (SELECT id FROM conversations WHERE user_id = ?)",
                    [$ownerId]
                );
        });
    }

    public function hasTag(string $tag): bool
    {
        return (bool) (($this->metadata['tags'][$tag] ?? false));
    }

    public function toggleTag(string $tag): bool
    {
        $tag = trim(mb_strtolower($tag));
        if ($tag === '') {
            return false;
        }

        $meta = $this->metadata ?? [];
        $tags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
        $enabled = ! (bool) ($tags[$tag] ?? false);

        if ($enabled) {
            $tags[$tag] = true;

            // Payment tags are mutually exclusive.
            if ($tag === self::TAG_PAID) {
                unset($tags[self::TAG_PENDING_PAYMENT]);
            } elseif ($tag === self::TAG_PENDING_PAYMENT) {
                unset($tags[self::TAG_PAID]);
            }
        } else {
            unset($tags[$tag]);
        }

        $meta['tags'] = $tags;
        $this->update(['metadata' => $meta]);

        return $enabled;
    }

    public function activeTags(): array
    {
        $tags = [];
        $meta = is_array($this->metadata) ? $this->metadata : [];
        $metaTags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
        foreach ($metaTags as $tag => $val) {
            if (!empty($val)) {
                $tags[] = (string) $tag;
            }
        }

        return array_values(array_unique($tags));
    }

    public function tagLabel(string $tag): string
    {
        return match ($tag) {
            self::TAG_PENDING_PAYMENT => 'Pending (Payment)',
            self::TAG_PAID => 'Paid',
            self::TAG_IMPORTANT => 'Important',
            default => ucwords(str_replace(['_', '-'], ' ', $tag)),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_ARCHIVED => 'Archived',
            default => 'Active',
        };
    }

    public function setTag(string $tag, bool $enabled): bool
    {
        $tag = trim(mb_strtolower($tag));
        if ($tag === '') {
            return false;
        }

        $meta = $this->metadata ?? [];
        $tags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];

        if ($enabled) {
            $tags[$tag] = true;
            if ($tag === self::TAG_PAID) {
                unset($tags[self::TAG_PENDING_PAYMENT]);
            } elseif ($tag === self::TAG_PENDING_PAYMENT) {
                unset($tags[self::TAG_PAID]);
            }
        } else {
            unset($tags[$tag]);
        }

        $meta['tags'] = $tags;
        $this->update(['metadata' => $meta]);

        return $enabled;
    }

    public function close(): bool
    {
        return $this->update(['status' => self::STATUS_CLOSED]);
    }

    public function archive(): bool
    {
        return $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    public function reopen(): bool
    {
        return $this->update(['status' => self::STATUS_OPEN]);
    }

    public function getActiveTagsAttribute(): array
    {
        return $this->activeTags();
    }

    public function getTagsAttribute(): array
    {
        return $this->activeTags();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusLabel();
    }

    public function getIsImportantAttribute(): bool
    {
        return $this->hasTag(self::TAG_IMPORTANT);
    }

    public function getImportantAttribute(): bool
    {
        return $this->hasTag(self::TAG_IMPORTANT);
    }

    public function getIsClosedAttribute(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function markAsRead()
    {
        $this->update([
            'unread_count' => 0,
            'last_read_at' => now(),
        ]);
    }

    /**
     * Whether AI auto-reply is allowed for this chat (per-contact override).
     */
    public function aiReplyEnabled(): bool
    {
        $value = $this->metadata['ai_reply_disabled'] ?? null;
        if ($value === true || $value === 1 || $value === '1') {
            return false;
        }
        if (is_string($value) && strtolower(trim($value)) === 'true') {
            return false;
        }

        return true;
    }

    public function incrementUnread()
    {
        $this->increment('unread_count');
        $this->touch('last_message_at');
    }

    public function pinnedMessageId(): ?int
    {
        $id = $this->metadata['pinned_message']['message_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function pinnedMessage(): ?WhatsappMessage
    {
        $messageId = $this->pinnedMessageId();
        if (! $messageId) {
            return null;
        }

        return WhatsappMessage::withTrashed()
            ->where('conversation_id', $this->id)
            ->where('id', $messageId)
            ->first();
    }

    public function pinMessage(WhatsappMessage $message, ?int $userId = null): bool
    {
        if ($message->conversation_id !== $this->id || $message->message_type === 'reaction') {
            return false;
        }

        $meta = $this->metadata ?? [];
        $meta['pinned_message'] = [
            'message_id' => $message->id,
            'pinned_at' => now()->toIso8601String(),
            'pinned_by' => $userId,
        ];
        $this->update(['metadata' => $meta]);

        return true;
    }

    public function unpinMessage(): void
    {
        $meta = $this->metadata ?? [];
        unset($meta['pinned_message']);
        $this->update(['metadata' => $meta]);
    }

    public function pinnedMessagePreview(): ?array
    {
        $message = $this->pinnedMessage();
        if (! $message || $message->trashed() || $message->isRevoked()) {
            if ($this->pinnedMessageId()) {
                $this->unpinMessage();
            }

            return null;
        }

        return [
            'message_id' => $message->id,
            'text' => $message->replyPreviewText(),
            'message_type' => (string) $message->message_type,
            'direction' => (string) $message->direction,
            'author' => $message->direction === 'outgoing'
                ? 'You'
                : ($this->contact_name ?? $this->phone_number ?? 'Contact'),
            'media_url' => $message->displayMediaUrl(),
            'revoked' => $message->isRevoked(),
        ];
    }
}
