<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Lead extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'source',
        'whatsapp',
        'address',
        'image',
        'company_name',
        'sic_code',
        'status',
        'lead_source_id',
        'lead_stage_id',
        'assigned_user_id',
        'user_id',
        'userId',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_converted',
        'converted_customer_id',
        'notes',
    ];

    protected $casts = [
        'is_converted' => 'boolean',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function whatsappConversation()
    {
        return $this->hasOne(WhatsappConversation::class);
    }

    public function whatsappMessages()
    {
        return $this->hasManyThrough(WhatsappMessage::class, WhatsappConversation::class);
    }

    public function whatsappFollowups()
    {
        return $this->hasMany(WhatsappFollowup::class);
    }

    public function notes()
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function tags()
    {
        return $this->belongsToMany(LeadTag::class, 'lead_tag_pivot', 'lead_id', 'lead_tag_id')
            ->withTimestamps()
            ->withPivot('created_by');
    }

    public function isWhatsAppLead(): bool
    {
        if (strcasecmp(trim((string) $this->source), 'WhatsApp') === 0) {
            return true;
        }

        return filled($this->whatsapp);
    }

    public function resolveWhatsappConversation(): ?WhatsappConversation
    {
        $byFk = $this->whatsappConversation()->first();
        if ($byFk) {
            return $byFk;
        }

        return $this->findWhatsappConversationByNormalizedPhone();
    }

    protected function findWhatsappConversationByNormalizedPhone(): ?WhatsappConversation
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return null;
        }

        $raw = $this->whatsapp ?: $this->phone;
        if (! filled($raw)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits)) {
            $digits = '91' . $digits;
        }

        return WhatsappConversation::query()
            ->where('phone_number', $digits)
            ->first();
    }

    public function scopeWithWhatsApp($query)
    {
        return $query->whereNotNull('whatsapp');
    }

    public function scopeWithTag($query, $tagName)
    {
        return $query->whereHas('tags', function ($q) use ($tagName) {
            $q->where('name', $tagName);
        });
    }
}
