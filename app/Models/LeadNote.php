<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadNote extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'lead_notes';

    protected $fillable = [
        'lead_id',
        'created_by',
        'note',
        'note_type',
        'is_private',
        'metadata',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'metadata' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('note_type', $type);
    }
}
