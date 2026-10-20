<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletOrder extends Model
{
    protected $fillable = [
        'userId',
        'razorpay_order_id',
        'payment_link_id',
        'payment_link_url',
        'payment_type',
        'payment_method',
        'receipt',
        'invoice_number',
        'amount',
        'amount_paise',
        'base_amount',
        'gst_percentage',
        'gst_amount',
        'total_amount',
        'package_id',
        'currency',
        'status',
        'razorpay_payment_id',
        'razorpay_signature',
        'notes',
    ];

    protected $appends = [
        'invoice_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'gst_percentage' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paise' => 'integer',
        'package_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_order_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function getInvoiceNumberAttribute(): string
    {
        if (!empty($this->attributes['invoice_number'])) {
            return $this->attributes['invoice_number'];
        }
        $year = $this->created_at ? $this->created_at->format('Y') : date('Y');
        return 'INV-' . $year . '-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function getParsedNotesAttribute(): array
    {
        if (empty($this->attributes['notes'])) {
            return [];
        }
        if (is_array($this->attributes['notes'])) {
            return $this->attributes['notes'];
        }
        $decoded = json_decode((string) $this->attributes['notes'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getIsPaidAttribute(): bool
    {
        return strtolower((string) ($this->attributes['status'] ?? '')) === 'paid';
    }
}
