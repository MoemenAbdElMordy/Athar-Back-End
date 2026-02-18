<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessibilityReport extends Model
{
    protected $fillable = [
        'location_id',
        'verified',
        'wide_entrance',
        'wheelchair_accessible',
        'elevator_available',
        'ramp_available',
        'parking',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
