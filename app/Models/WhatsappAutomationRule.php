<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappAutomationRule extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'whatsapp_automation_rules';

    protected $fillable = [
        'userId',
        'name',
        'trigger_type',
        'trigger_keywords',
        'template_id',
        'response_message',
        'is_active',
        'priority',
        'execution_count',
        'last_executed_at',
        'conditions',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conditions' => 'array',
        'metadata' => 'array',
        'last_executed_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTriggerType($query, $type)
    {
        return $query->where('trigger_type', $type);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function matchesKeyword($message)
    {
        if (!$this->trigger_keywords) {
            return false;
        }

        $keywords = array_map('strtolower', explode(',', $this->trigger_keywords));
        $messageLower = strtolower($message);

        foreach ($keywords as $keyword) {
            if (str_contains($messageLower, trim($keyword))) {
                return true;
            }
        }

        return false;
    }

    public function incrementExecution()
    {
        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);
    }
}
