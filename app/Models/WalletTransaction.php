<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'userId',
        'type',
        'source',
        'category',
        'amount',
        'balance_after',
        'reference_id',
        'wallet_order_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    protected $appends = [
        'category',
    ];

    public function getCategoryAttribute(): ?string
    {
        if (!empty($this->attributes['category'])) {
            return strtoupper($this->attributes['category']);
        }

        $desc = (string) ($this->attributes['description'] ?? '');
        if (preg_match('/\b(MARKETING|UTILITY|AUTHENTICATION)\b/i', $desc, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function order()
    {
        return $this->belongsTo(WalletOrder::class, 'wallet_order_id');
    }

    public function walletOrder()
    {
        return $this->belongsTo(WalletOrder::class, 'wallet_order_id');
    }
}
