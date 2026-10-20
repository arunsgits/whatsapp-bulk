<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulkMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'userId',
        'name',
        'templateId',
        'variables',
        'headerVariables',
        'contactIds',
        'contactId',
        'scheduleAt',
        'status',
        'sendingDate',
        'sentStatus',
    ];

    protected $casts = [
        'variables' => 'array',
        'headerVariables' => 'array',
        'contactIds' => 'array',
        'sentStatus' => 'array',
        'scheduleAt' => 'datetime',
        'sendingDate' => 'datetime',
    ];

    /**
     * Get the user that owns the bulk message.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Get the template for the bulk message.
     */
    public function template()
    {
        return $this->belongsTo(Template::class, 'templateId');
    }

    /**
     * Get the contact for the bulk message.
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contactId');
    }

    /**
     * Get the delivery statuses for the bulk message.
     */
    public function deliveryStatuses()
    {
        return $this->hasMany(MessageDeliveryStatus::class);
    }

    /**
     * Get delivery status for a specific contact
     */
    public function getDeliveryStatusForContact($contactId)
    {
        return $this->deliveryStatuses()->where('contact_id', $contactId)->first();
    }

    /**
     * Get delivery status summary
     */
    public function getDeliveryStatusSummary()
    {
        $statuses = $this->deliveryStatuses()->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $pending = (int) ($statuses['pending'] ?? 0);
        $processing = (int) ($statuses['processing'] ?? 0);
        $sent = (int) ($statuses['sent'] ?? 0);
        $delivered = (int) ($statuses['delivered'] ?? 0);
        $read = (int) ($statuses['read'] ?? 0);
        $failed = (int) ($statuses['failed'] ?? 0);
        $total = array_sum($statuses);

        $hasWebhookUpdates = ($delivered + $read + $failed) > 0;

        return [
            'pending' => $pending,
            'processing' => $processing,
            'sent' => $sent,
            'delivered' => $delivered,
            'read' => $read,
            'failed' => $failed,
            'total' => $total,
            'total_sent' => $sent + $delivered + $read,
            'total_delivered' => $delivered + $read,
            'successful' => $sent + $delivered + $read,
            'has_webhook_updates' => $hasWebhookUpdates,
            'webhook_hint' => (!$hasWebhookUpdates && $sent > 0)
                ? 'Messages were sent to WhatsApp Cloud. Awaiting delivery receipts from Meta webhook.'
                : null,
        ];
    }
}
