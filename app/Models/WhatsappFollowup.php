<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappFollowup extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'whatsapp_followups';

    protected $fillable = [
        'lead_id',
        'conversation_id',
        'assigned_user_id',
        'title',
        'description',
        'status',
        'due_date',
        'completed_at',
        'template_id',
        'message',
        'is_sent',
        'sent_at',
        'metadata',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
        'metadata' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function conversation()
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->pending()->where('due_date', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->pending()->whereDate('due_date', today());
    }

    public function scopeDueThisWeek($query)
    {
        return $query->pending()->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsSent()
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }
}
