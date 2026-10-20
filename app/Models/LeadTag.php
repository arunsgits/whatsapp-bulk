<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadTag extends Model
{
    use SoftDeletes, Blameable;

    protected $table = 'lead_tags';

    protected $fillable = [
        'name',
        'color',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function leads()
    {
        return $this->belongsToMany(Lead::class, 'lead_tag_pivot', 'lead_tag_id', 'lead_id')
            ->withTimestamps()
            ->withPivot('created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
