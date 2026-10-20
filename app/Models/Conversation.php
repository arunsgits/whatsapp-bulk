<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'whatsapp_id',
        'phone_number',
        'contact_name',
        'user_id',
        'status',
        'last_message_at',
        'last_message_preview',
        'is_unread',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'is_unread' => 'boolean'
    ];

    protected $appends = [
        'unread_count',
        'status_label',
        'is_closed',
        'is_archived',
        'tags',
        'active_tags',
        'is_important',
        'important',
    ];

    /**
     * Get the user that owns the conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages for the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('whatsapp_timestamp', 'asc');
    }

    /**
     * Get the latest message for the conversation.
     */
    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest('whatsapp_timestamp');
    }

    /**
     * Get unread messages count.
     */
    public function getUnreadCountAttribute(): int
    {
        if (array_key_exists('unread_count', $this->attributes)) {
            return (int) $this->attributes['unread_count'];
        }
        $count = $this->messages()->where('direction', 'inbound')->where('is_read', false)->count();
        if ($count === 0 && !empty($this->attributes['is_unread'])) {
            return 1;
        }
        return $count;
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(): void
    {
        $this->update(['is_unread' => false]);
        $this->messages()->where('direction', 'inbound')->update(['is_read' => true]);
    }

    /**
     * Get the contact associated with this conversation by phone number.
     */
    public function contact()
    {
        return $this->hasOne(Contact::class, 'phone', 'phone_number')
            ->where('userId', $this->user_id);
    }

    public function getStatusLabelAttribute(): string
    {
        if (isset($this->attributes['status_label'])) {
            return (string) $this->attributes['status_label'];
        }
        $status = strtolower(trim((string) ($this->attributes['status'] ?? 'active')));
        return match ($status) {
            'closed', 'close' => 'Closed',
            'archived', 'archive' => 'Archived',
            'pending', 'pending_payment' => 'Pending',
            default => 'Active',
        };
    }

    public function getIsClosedAttribute(): bool
    {
        if (isset($this->attributes['is_closed'])) {
            return (bool) $this->attributes['is_closed'];
        }
        $status = strtolower(trim((string) ($this->attributes['status'] ?? 'active')));
        return in_array($status, ['closed', 'close'], true);
    }

    public function getIsArchivedAttribute(): bool
    {
        if (isset($this->attributes['is_archived'])) {
            return (bool) $this->attributes['is_archived'];
        }
        $status = strtolower(trim((string) ($this->attributes['status'] ?? 'active')));
        return in_array($status, ['archived', 'archive'], true);
    }

    public function getTagsAttribute(): array
    {
        if (isset($this->attributes['tags']) && is_array($this->attributes['tags'])) {
            return array_values($this->attributes['tags']);
        }
        $meta = is_array($this->metadata) ? $this->metadata : [];
        $metaTags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
        $tags = [];
        foreach ($metaTags as $tag => $val) {
            if (!empty($val)) {
                $tags[] = (string) $tag;
            }
        }
        return array_values(array_unique($tags));
    }

    public function getActiveTagsAttribute(): array
    {
        return $this->getTagsAttribute();
    }

    public function getIsImportantAttribute(): bool
    {
        if (isset($this->attributes['is_important'])) {
            return (bool) $this->attributes['is_important'];
        }
        return in_array('important', $this->getTagsAttribute(), true);
    }

    public function getImportantAttribute(): bool
    {
        return $this->getIsImportantAttribute();
    }
}
