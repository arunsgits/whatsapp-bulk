<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'bulk_message_id',
        'contact_id',
        'status',
        'whatsapp_message_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'metadata',
        'wallet_charged',
        'wallet_amount',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'wallet_charged' => 'boolean',
        'wallet_amount' => 'decimal:4',
    ];

    protected $appends = [
        'status_label',
        'status_message',
        'status_description',
        'status_badge_color',
        'display_error',
        'delivery_reason',
        'reason',
        'template_name',
        'contact_name',
        'contact_phone',
        'sending_date',
    ];

    /**
     * Human-friendly label for current delivery status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match (strtolower((string) $this->status)) {
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'read' => 'Read',
            'failed' => 'Failed',
            'processing' => 'Processing',
            'pending' => 'Pending',
            default => ucfirst((string) ($this->status ?: 'Unknown')),
        };
    }

    /**
     * UI badge color token corresponding to status.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match (strtolower((string) $this->status)) {
            'read' => 'info',
            'delivered' => 'primary',
            'sent' => 'success',
            'failed' => 'danger',
            'processing' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Formatted human-readable error explanation if delivery failed.
     */
    public function getDisplayErrorAttribute(): ?string
    {
        if ($this->status !== 'failed' && empty($this->error_message)) {
            return null;
        }

        $rawError = $this->error_message;
        if (empty($rawError) && is_array($this->metadata)) {
            $rawError = $this->metadata['error'] ?? $this->metadata['error_message'] ?? null;
        }

        if (empty($rawError)) {
            return $this->status === 'failed' ? 'Message delivery failed on Meta WhatsApp.' : null;
        }

        if (is_string($rawError)) {
            $trimmed = trim($rawError);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                    $rawError = $decoded;
                }
            }
        }

        return \App\Services\WhatsAppService::formatMetaErrorMessage($rawError);
    }

    /**
     * Detailed status message / description explaining the state of the message.
     */
    public function getStatusDescriptionAttribute(): string
    {
        return $this->getDeliveryReasonAttribute();
    }

    /**
     * Detailed reason explaining why the message is in its current state (sent, delivered, failed, etc.).
     */
    public function getDeliveryReasonAttribute(): string
    {
        $status = strtolower((string) $this->status);

        if ($status === 'read') {
            return 'Message was delivered and read by recipient' . ($this->read_at ? ' on ' . $this->read_at->format('d/m/Y h:i A') : '') . '.';
        }

        if ($status === 'delivered') {
            return 'Message was delivered to recipient handset' . ($this->delivered_at ? ' on ' . $this->delivered_at->format('d/m/Y h:i A') : '') . '.';
        }

        if ($status === 'failed') {
            $err = $this->display_error ?: ($this->error_message ?: 'Delivery failed on WhatsApp.');
            return 'Delivery failed: ' . $err;
        }

        if ($status === 'sent') {
            $diffMins = $this->sent_at ? $this->sent_at->diffInMinutes(now()) : 0;
            if ($diffMins > 10) {
                return "Accepted by Meta WhatsApp Cloud ({$diffMins} mins ago), but handset delivery is pending. Possible reasons: recipient device is switched off/offline, recipient has blocked this business number, or Meta marketing pacing is delaying delivery.";
            }
            return 'Accepted by Meta WhatsApp Cloud and queued for handset delivery. Waiting for recipient device to connect to WhatsApp.';
        }

        if ($status === 'processing') {
            return 'Message is currently being dispatched to WhatsApp Cloud API.';
        }

        if ($status === 'pending') {
            return 'Message is queued in campaign and waiting to be sent.';
        }

        return ucfirst($status ?: 'Unknown state');
    }

    /**
     * Alias for delivery_reason.
     */
    public function getReasonAttribute(): string
    {
        return $this->getDeliveryReasonAttribute();
    }

    /**
     * Alias for status_description to easily bind to table columns.
     */
    public function getStatusMessageAttribute(): string
    {
        return $this->getStatusDescriptionAttribute();
    }

    /**
     * Template name attached to this delivery message.
     */
    public function getTemplateNameAttribute(): ?string
    {
        if ($this->relationLoaded('bulkMessage') && $this->bulkMessage) {
            if ($this->bulkMessage->relationLoaded('template') && $this->bulkMessage->template) {
                return $this->bulkMessage->template->name;
            }
        }

        return $this->metadata['template_name'] ?? null;
    }

    /**
     * Contact name attached to this delivery message.
     */
    public function getContactNameAttribute(): ?string
    {
        if ($this->relationLoaded('contact') && $this->contact) {
            return $this->contact->name;
        }

        return $this->metadata['contact_name'] ?? null;
    }

    /**
     * Contact phone number.
     */
    public function getContactPhoneAttribute(): ?string
    {
        if ($this->relationLoaded('contact') && $this->contact) {
            return $this->contact->phone;
        }

        return $this->metadata['contact_phone'] ?? $this->metadata['recipient_id'] ?? null;
    }

    /**
     * Formatted sending date string (e.g. 16/10/2026).
     */
    public function getSendingDateAttribute(): ?string
    {
        $dt = $this->sent_at ?: $this->created_at;
        return $dt ? $dt->format('d/m/Y') : null;
    }

    /**
     * Get the bulk message that owns the delivery status.
     */
    public function bulkMessage()
    {
        return $this->belongsTo(BulkMessage::class);
    }

    /**
     * Get the contact that owns the delivery status.
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by bulk message
     */
    public function scopeByBulkMessage($query, $bulkMessageId)
    {
        return $query->where('bulk_message_id', $bulkMessageId);
    }

    /**
     * Update status with timestamp
     */
    public function updateStatus($status, $metadata = null)
    {
        $updateData = ['status' => $status];

        switch ($status) {
            case 'sent':
                $updateData['sent_at'] = now();
                break;
            case 'delivered':
                $updateData['delivered_at'] = now();
                break;
            case 'read':
                $updateData['read_at'] = now();
                break;
            case 'failed':
                $updateData['failed_at'] = now();
                break;
        }

        if ($metadata) {
            // Extract whatsapp_message_id from metadata if present
            if (isset($metadata['whatsapp_message_id']) && !empty($metadata['whatsapp_message_id'])) {
                $updateData['whatsapp_message_id'] = $metadata['whatsapp_message_id'];
            }
            
            // Extract error_message from metadata if present and format it cleanly
            if (isset($metadata['error_message']) && !empty($metadata['error_message'])) {
                $updateData['error_message'] = is_array($metadata['error_message'])
                    ? \App\Services\WhatsAppService::formatMetaErrorMessage($metadata['error_message'])
                    : $metadata['error_message'];
            } elseif (isset($metadata['error']) && !empty($metadata['error'])) {
                $updateData['error_message'] = \App\Services\WhatsAppService::formatMetaErrorMessage($metadata['error']);
            }
            
            $updateData['metadata'] = $metadata;
        }

        $this->update($updateData);
    }
}
