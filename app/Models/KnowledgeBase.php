<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBase extends Model
{
    use HasFactory;

    protected $table = 'knowledge_bases';

    protected $fillable = [
        'user_id',
        'custom_id',
        'question',
        'answer',
        'category',
        'keywords',
        'source',
        'source_url',
        'is_published',
        'is_enabled',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_published' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            if (empty($item->custom_id)) {
                $item->custom_id = 'faq_' . round(microtime(true) * 1000) . mt_rand(10, 99);
            }
            if (empty($item->category)) {
                $item->category = 'General';
            }
            if (empty($item->source)) {
                $item->source = 'Manual';
            }
        });
    }

    /**
     * Relationship: Owner User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for user queries.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for active/published knowledge items used by Auto AI.
     */
    public function scopeActive($query)
    {
        return $query->where('is_published', true)->where('is_enabled', true);
    }

    /**
     * Format payload to match the frontend developer specification.
     */
    public function toApiResponse(): array
    {
        return [
            'id' => $this->custom_id ?: ('faq_' . $this->id),
            'numeric_id' => $this->id,
            'question' => (string) $this->question,
            'answer' => (string) $this->answer,
            'category' => (string) ($this->category ?: 'General'),
            'keywords' => is_array($this->keywords) ? array_values($this->keywords) : [],
            'source' => (string) ($this->source ?: 'Manual'),
            'source_url' => $this->source_url,
            'is_published' => (bool) $this->is_published,
            'is_enabled' => (bool) $this->is_enabled,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
