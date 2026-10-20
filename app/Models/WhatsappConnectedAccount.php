<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappConnectedAccount extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_connected_accounts';

    protected $fillable = [
        'userId',
        'account_name',
        'session_id',
        'phone_number',
        'profile_name',
        'profile_picture',
        'status',
        'qr_code',
        'last_connected_at',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
    ];

    /**
     * Get the user that owns this connected WhatsApp account.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Scope for filtering by user/company ID.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('userId', $userId);
    }

    /**
     * Scope for active/connected accounts.
     */
    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }
}
