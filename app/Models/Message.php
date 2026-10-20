<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'whatsapp_message_id',
        'direction',
        'type',
        'content',
        'media_url',
        'media_type',
        'media_filename',
        'metadata',
        'status',
        'whatsapp_timestamp',
        'is_read',
        // Runtime-only virtual properties set by controllers (not stored in DB)
        'media_display',
        'media_display_url',
        'media_download_url',
        'can_react',
        'can_reply',
        'react_message_id',
        'reply_to_message_id',
        'inbox_conversation_id',
        'react_endpoint',
        'reply_endpoint',
        'capabilities',
        'meta_message_id',
        'reactions',
        'reaction_summary',
        'reaction_emojis',
        'message_type',
        'reply_to',
        'is_reply',
        'replied_to_message_id',
        'location',
    ];

    protected $casts = [
        'metadata'        => 'array',
        'media_display'   => 'array',
        'whatsapp_timestamp' => 'datetime',
        'is_read'         => 'boolean',
    ];

    /**
     * Get the conversation that owns the message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Check if message is within 24 hours window for free-form messaging.
     */
    public function isWithin24Hours(): bool
    {
        return $this->whatsapp_timestamp->diffInHours(now()) <= 24;
    }

    /**
     * Get formatted timestamp.
     */
    public function getFormattedTimestampAttribute(): string
    {
        return $this->whatsapp_timestamp->format('Y-m-d H:i:s');
    }
}
