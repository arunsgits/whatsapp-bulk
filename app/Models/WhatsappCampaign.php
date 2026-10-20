<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappCampaign extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'userId',
        'name',
        'description',
        'template_id',
        'type',
        'message',
        'status',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
        'filters',
        'metadata',
    ];

    protected $casts = [
        'filters' => 'array',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'sending']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        return round(($this->sent_count / $this->total_recipients) * 100, 2);
    }

    public function getSuccessRateAttribute()
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->delivered_count / $this->sent_count) * 100, 2);
    }

    public function markAsSending()
    {
        $this->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function incrementSent()
    {
        $this->increment('sent_count');
    }

    public function incrementDelivered()
    {
        $this->increment('delivered_count');
    }

    public function incrementRead()
    {
        $this->increment('read_count');
    }

    public function incrementFailed()
    {
        $this->increment('failed_count');
    }
}
